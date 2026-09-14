<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RB Concept • Template 1</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root{
        --bg: #f5f2eb;
        --surface: rgba(255,255,255,0.86);
        --surface-2: rgba(255,255,255,0.94);
        --border: rgba(17,24,39,0.10);
        --text: rgba(17,24,39,0.92);
        --muted: rgba(17,24,39,0.68);
        --brand: #2f2a24;
        --brand-2: #9a855f;
        --shadow: 0 18px 50px rgba(17,24,39,0.14);
        --radius: 18px;
        --max: 1120px;
        --img-1: url("/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg");
      }

      *{ box-sizing: border-box; }
      html, body{ height: 100%; }
      body{
        margin: 0;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        color: var(--text);
        background:
          radial-gradient(900px 520px at 15% 0%, rgba(154,133,95,0.22), transparent 62%),
          radial-gradient(900px 520px at 85% 8%, rgba(47,42,36,0.18), transparent 62%),
          linear-gradient(180deg, #ffffff 0%, var(--bg) 60%, #ffffff 100%);
      }

      a{ color: inherit; text-decoration: none; }
      img{ display:block; max-width:100%; }
      .container{ width: min(100% - 32px, var(--max)); margin: 0 auto; }

      .topbar{
        position: sticky;
        top: 0;
        z-index: 50;
        backdrop-filter: blur(12px);
        background: linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,255,255,0.58));
        border-bottom: 1px solid var(--border);
      }
      .nav{
        position: relative;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 16px;
        padding: 14px 0;
      }
      .brand{
        display:flex;
        align-items:center;
        gap: 10px;
        min-width: max-content;
      }
      .brand-mark{
        width: 40px;
        height: 40px;
        border-radius: 14px;
        background:
          radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.72), transparent 60%),
          linear-gradient(135deg, rgba(154,133,95,1), rgba(47,42,36,1));
        box-shadow: 0 18px 50px rgba(47,42,36,0.18);
      }
      .brand-title{
        line-height: 1.1;
      }
      .brand-title strong{
        font-family: "Playfair Display", serif;
        letter-spacing: -0.02em;
        font-weight: 700;
      }
      .brand-title span{
        display:block;
        margin-top: 2px;
        font-size: 12px;
        color: var(--muted);
      }

      .navlinks{
        display:flex;
        align-items:center;
        gap: 16px;
      }
      .navlinks a{
        font-size: 14px;
        color: var(--muted);
        padding: 10px 10px;
        border-radius: 12px;
        transition: background 160ms ease, color 160ms ease;
      }
      .navlinks a:hover{
        background: rgba(17,24,39,0.06);
        color: var(--text);
      }
      .nav-cta{
        display:flex;
        align-items:center;
        gap: 10px;
        min-width: max-content;
      }
      .nav-toggle{
        display:none;
        width: 42px;
        height: 42px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.78);
        align-items:center;
        justify-content:center;
        cursor: pointer;
      }
      .pill{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 13px;
      }
      .btn{
        appearance: none;
        border: 0;
        cursor: pointer;
        border-radius: 999px;
        padding: 11px 14px;
        font-weight: 600;
        font-size: 14px;
        display:inline-flex;
        align-items:center;
        gap: 10px;
        transition: transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease;
      }
      .btn:active{ transform: translateY(1px); }
      .btn-primary{
        background: rgba(47,42,36,0.90);
        color: rgba(255,255,255,0.95);
        box-shadow: 0 18px 60px rgba(47,42,36,0.20);
      }
      .btn-primary:hover{ filter: brightness(1.05); }
      .btn-ghost{
        background: rgba(255,255,255,0.72);
        color: var(--text);
        border: 1px solid var(--border);
      }
      .btn-ghost:hover{ background: rgba(255,255,255,0.90); }

      .hero{
        position: relative;
        overflow: hidden;
      }
      .hero-bg{
        position:absolute;
        inset: 0;
        background:
          linear-gradient(90deg, rgba(0,0,0,0.52) 0%, rgba(0,0,0,0.24) 42%, rgba(0,0,0,0.08) 72%, rgba(0,0,0,0.22) 100%),
          var(--img-1) center/cover no-repeat;
        transform: scale(1.03);
        filter: saturate(0.98) contrast(1.02);
      }
      .hero-inner{
        position: relative;
        padding: 72px 0 44px;
      }
      .hero-grid{
        display:grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap: 28px;
        align-items: end;
      }
      .hero-copy{
        padding: 22px 20px;
        border-radius: var(--radius);
        background: rgba(0,0,0,0.26);
        border: 1px solid rgba(255,255,255,0.14);
        box-shadow: 0 28px 80px rgba(0,0,0,0.22);
        color: rgba(255,255,255,0.96);
        backdrop-filter: blur(12px);
      }
      .hero-kicker{
        display:inline-flex;
        align-items:center;
        gap: 10px;
        font-weight: 600;
        font-size: 12px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.86);
      }
      .hero-kicker .dot{
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: rgba(154,133,95,0.95);
        box-shadow: 0 0 0 4px rgba(154,133,95,0.18);
      }
      .hero-copy h1{
        margin: 0 0 10px;
        font-family: "Playfair Display", serif;
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.1;
        font-size: clamp(32px, 3.2vw, 46px);
      }
      .hero-copy p{
        margin: 0;
        color: rgba(255,255,255,0.82);
        line-height: 1.7;
        max-width: 58ch;
      }
      .hero-actions{
        margin-top: 18px;
        display:flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
      }
      .hero-actions .btn-ghost{
        border-color: rgba(255,255,255,0.22);
        background: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.94);
      }
      .hero-actions .btn-ghost:hover{
        background: rgba(255,255,255,0.18);
      }
      .hero-card{
        border-radius: var(--radius);
        background: rgba(255,255,255,0.86);
        border: 1px solid rgba(255,255,255,0.34);
        box-shadow: 0 24px 80px rgba(0,0,0,0.22);
        overflow: hidden;
        backdrop-filter: blur(12px);
      }
      .hero-card .body{
        padding: 18px;
        display:grid;
        gap: 10px;
      }
      .hero-card b{
        font-family: "Playfair Display", serif;
        font-weight: 700;
      }
      .hero-card p{
        margin: 0;
        color: var(--muted);
        line-height: 1.6;
        font-size: 14px;
      }
      .hero-card .mini{
        display:flex;
        gap: 10px;
        align-items:center;
        margin-top: 8px;
      }
      .mini .thumb{
        width: 68px;
        height: 56px;
        border-radius: 14px;
        background: url("/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg") center/cover no-repeat;
        border: 1px solid rgba(17,24,39,0.10);
      }
      .mini .btn{
        padding: 10px 12px;
        font-size: 13px;
      }

      .feature-strip{
        background: rgba(255,255,255,0.78);
        border-top: 1px solid rgba(255,255,255,0.40);
        border-bottom: 1px solid var(--border);
        backdrop-filter: blur(12px);
      }
      .feature-grid{
        display:grid;
        grid-template-columns: 1.2fr 1.8fr;
        gap: 18px;
        padding: 18px 0;
        align-items: stretch;
      }
      .intro-card{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        padding: 16px 16px;
        box-shadow: var(--shadow);
      }
      .intro-card h3{
        margin: 0 0 6px;
        font-family: "Playfair Display", serif;
        letter-spacing: -0.02em;
        font-size: 18px;
      }
      .intro-card p{
        margin: 0;
        color: var(--muted);
        line-height: 1.6;
        font-size: 13px;
      }
      .intro-card .btn{
        margin-top: 12px;
        padding: 10px 12px;
        font-size: 13px;
      }

      .features{
        display:grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
      }
      .feature{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.74);
        padding: 14px 14px;
      }
      .feature i{
        width: 42px;
        height: 42px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius: 14px;
        background: rgba(154,133,95,0.14);
        border: 1px solid rgba(154,133,95,0.18);
        color: rgba(47,42,36,0.92);
      }
      .feature b{
        display:block;
        margin-top: 10px;
        font-size: 13px;
      }
      .feature span{
        display:block;
        margin-top: 4px;
        font-size: 12px;
        color: var(--muted);
        line-height: 1.55;
      }

      .section{
        padding: 34px 0;
      }
      .section h2{
        margin: 0 0 14px;
        text-align:center;
        font-family: "Playfair Display", serif;
        font-size: 22px;
        letter-spacing: -0.02em;
      }
      .section-lead{
        text-align:center;
        color: var(--muted);
        max-width: 70ch;
        margin: 0 auto 16px;
        line-height: 1.7;
        font-size: 14px;
      }
      .grid{
        display:grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
      }
      .card{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow:hidden;
        transition: transform 180ms ease, box-shadow 180ms ease, filter 180ms ease;
      }
      .card:hover{
        transform: translateY(-2px);
        box-shadow: 0 24px 70px rgba(17,24,39,0.16);
      }
      .card .img{
        height: 140px;
        background: var(--img-1) center/cover no-repeat;
      }
      .card .body{
        padding: 12px 12px;
        display:grid;
        gap: 6px;
      }
      .card b{ font-size: 13px; }
      .card small{ color: var(--muted); }
      .center-actions{
        display:flex;
        justify-content:center;
        margin-top: 14px;
      }

      .grid-3{
        display:grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
      }
      .news{
        display:grid;
        grid-template-columns: 2fr 1fr;
        gap: 14px;
        align-items: start;
      }
      .news .card .img{ height: 150px; }
      .contact{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: rgba(47,42,36,0.86);
        color: rgba(255,255,255,0.92);
        box-shadow: 0 22px 70px rgba(0,0,0,0.22);
        padding: 16px;
      }
      .contact h3{
        margin: 0 0 8px;
        font-family: "Playfair Display", serif;
        font-size: 18px;
      }
      .contact .line{
        display:flex;
        gap: 10px;
        align-items:flex-start;
        margin-top: 10px;
        color: rgba(255,255,255,0.84);
        font-size: 13px;
        line-height: 1.5;
      }
      .contact i{
        margin-top: 2px;
        width: 18px;
        text-align:center;
        color: rgba(255,255,255,0.92);
      }

      .icon-gap{ margin-right: 8px; }
      .site-footer{
        margin-top: 34px;
        position: relative;
        overflow: hidden;
        color: rgba(255,255,255,0.90);
        background: #0f0f10;
      }
      .site-footer::before{
        content:"";
        position: absolute;
        inset: 0;
        background: var(--img-1) center/cover no-repeat;
        filter: saturate(0.85) contrast(1.10);
        opacity: 0.55;
        z-index: 0;
        pointer-events: none;
      }
      .site-footer::after{
        content:"";
        position:absolute;
        inset: 0;
        background:
          linear-gradient(180deg, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0.62) 55%, rgba(0,0,0,0.76) 100%);
        z-index: 0;
        pointer-events: none;
      }
      .footer-layer{ position: relative; z-index: 1; }
      .footer-cta{
        border-bottom: 1px solid rgba(255,255,255,0.10);
        background: rgba(0,0,0,0.34);
      }
      .footer-cta-inner{
        padding: 14px 0;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 12px;
        flex-wrap: wrap;
      }
      .footer-cta-inner b{
        font-family:"Playfair Display", serif;
        font-weight: 600;
        color: rgba(255,255,255,0.92);
      }
      .footer-cta .btn-ghost{
        border-color: rgba(255,255,255,0.18);
        background: rgba(255,255,255,0.10);
        color: rgba(255,255,255,0.92);
      }
      .footer-cta .btn-ghost:hover{
        background: rgba(255,255,255,0.16);
      }
      .footer-main{
        padding: 18px 0 10px;
      }
      .footer-cols{
        display:grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
      }
      .footer-col h4{
        margin: 0 0 10px;
        font-size: 14px;
        font-weight: 700;
        color: rgba(255,255,255,0.92);
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.10);
      }
      .footer-link{
        display:block;
        color: rgba(255,255,255,0.78);
        font-size: 13px;
        padding: 6px 0;
      }
      .footer-link:hover{
        color: rgba(255,255,255,0.92);
      }
      .footer-contact{
        display:grid;
        gap: 8px;
        color: rgba(255,255,255,0.78);
        font-size: 13px;
      }
      .footer-contact .item{
        display:flex;
        gap: 10px;
        align-items:flex-start;
        line-height: 1.5;
      }
      .footer-contact i{
        width: 18px;
        margin-top: 2px;
        text-align:center;
        color: rgba(255,255,255,0.90);
      }
      .footer-bottom{
        border-top: 1px solid rgba(255,255,255,0.10);
        padding: 12px 0 14px;
        text-align: center;
        color: rgba(255,255,255,0.70);
        font-size: 13px;
      }

      @media (max-width: 1024px){
        .hero-grid{ grid-template-columns: 1fr; }
        .feature-grid{ grid-template-columns: 1fr; }
        .features{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .news{ grid-template-columns: 1fr; }
        .footer-cols{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
      }
      @media (max-width: 680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{
          display:none;
          position: absolute;
          left: 16px;
          right: 16px;
          top: calc(100% + 10px);
          padding: 10px;
          border-radius: 16px;
          border: 1px solid var(--border);
          background: rgba(255,255,255,0.96);
          box-shadow: 0 26px 80px rgba(17,24,39,0.18);
          flex-direction: column;
          align-items: stretch;
          gap: 6px;
        }
        .navlinks a{
          padding: 12px 12px;
        }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .grid{ grid-template-columns: 1fr; }
        .grid-3{ grid-template-columns: 1fr; }
        .footer-cols{ grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body>
    <header class="topbar">
      <div class="container">
        <div class="nav">
          <a class="brand" href="/template1.php">
            <span class="brand-mark" aria-hidden="true"></span>
            <span class="brand-title">
              <strong>RB Concept</strong>
              <span>Thiết kế phòng tắm</span>
            </span>
          </a>
          <nav class="navlinks" aria-label="menu">
            <a href="/template1.php#home">Trang chủ</a>
            <a href="/san-pham.php">Sản phẩm</a>
            <a href="/du-an.php">Dự án</a>
            <a href="/blog.php">Blog</a>
            <a href="/lien-he.php">Liên hệ</a>
          </nav>
          <div class="nav-cta">
            <span class="pill"><i class="fa-solid fa-phone" aria-hidden="true"></i> Hotline: 0988 123 456</span>
            <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Nhận tư vấn</a>
            <button class="nav-toggle" type="button" aria-label="Mở menu" data-nav-toggle="1">
              <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </div>
    </header>

    <section class="hero" id="home">
      <div class="hero-bg" aria-hidden="true"></div>
      <div class="container hero-inner">
        <div class="hero-grid">
          <div class="hero-copy">
            <div class="hero-kicker"><span class="dot" aria-hidden="true"></span> Premium Bathroom Design</div>
            <h1>Thiết kế phòng tắm tinh tế<br>cho không gian sống hiện đại</h1>
            <p>Giải pháp bồn tắm, lavabo và phụ kiện cao cấp cho villa, khách sạn và resort. Tối ưu công năng, bền vững và thẩm mỹ.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="/san-pham.php"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Xem sản phẩm</a>
              <a class="btn btn-ghost" href="/du-an.php"><i class="fa-solid fa-images" aria-hidden="true"></i> Xem dự án</a>
            </div>
          </div>
          <div class="hero-card">
            <div class="body">
              <b>RB Concept</b>
              <p>Chất liệu bền vững, thiết kế tinh gọn, giải pháp cho dự án theo yêu cầu.</p>
              <div class="mini">
                <div class="thumb" aria-hidden="true"></div>
                <a class="btn btn-ghost" href="/lien-he.php"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Tìm hiểu thêm</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="feature-strip">
      <div class="container">
        <div class="feature-grid">
          <div class="intro-card">
            <h3>Giải pháp phòng tắm</h3>
            <p>Thiết kế tinh giản, chất liệu bền vững, phù hợp nhiều phong cách kiến trúc.</p>
            <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Liên hệ ngay</a>
          </div>
          <div class="features">
            <div class="feature">
              <i class="fa-solid fa-leaf" aria-hidden="true"></i>
              <b>Chất liệu bền vững</b>
              <span>Đá tự nhiên, composite, gốm cao cấp.</span>
            </div>
            <div class="feature">
              <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
              <b>Lavabo đa dạng</b>
              <span>Nhiều kiểu dáng: đặt bàn, treo tường.</span>
            </div>
            <div class="feature">
              <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
              <b>Thiết kế tinh gọn</b>
              <span>Phù hợp phong cách hiện đại.</span>
            </div>
            <div class="feature">
              <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
              <b>Giá tốt cho dự án</b>
              <span>Giải pháp đồng bộ cho nhiều hạng mục.</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section" id="san-pham">
      <div class="container">
        <h2>Sản phẩm nổi bật</h2>
        <p class="section-lead">Tuyển chọn bồn tắm &amp; lavabo cho không gian hiện đại. Chất liệu bền vững, hoàn thiện tinh xảo và dễ phối cảnh.</p>
        <div class="grid">
          <div class="card">
            <div class="img" style="background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
            <div class="body">
              <b>Bồn tắm đá tự nhiên</b>
              <small>Hoàn thiện bề mặt mịn</small>
            </div>
          </div>
          <div class="card">
            <div class="img" style="background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
            <div class="body">
              <b>Lavabo Terrazzo</b>
              <small>Phong cách hiện đại</small>
            </div>
          </div>
          <div class="card">
            <div class="img" style="background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
            <div class="body">
              <b>Bồn tắm Freestanding</b>
              <small>Đường nét tinh tế</small>
            </div>
          </div>
          <div class="card">
            <div class="img" style="background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
            <div class="body">
              <b>Lavabo đặt bàn</b>
              <small>Tối ưu không gian</small>
            </div>
          </div>
        </div>
        <div class="center-actions">
          <a class="btn btn-primary" href="#"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Xem tất cả sản phẩm</a>
        </div>
      </div>
    </section>

    <section class="section" id="du-an">
      <div class="container">
        <h2>Dự án thực tế</h2>
        <p class="section-lead">Một vài không gian đã triển khai cho villa, resort và khách sạn. Thiết kế đồng bộ theo concept và mặt bằng thực tế.</p>
        <div class="grid-3">
          <div class="card">
            <div class="img" style="height:170px;background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
            <div class="body">
              <b>Villa Bathroom</b>
              <small>Không gian sang trọng</small>
            </div>
          </div>
          <div class="card">
            <div class="img" style="height:170px;background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
            <div class="body">
              <b>Resort Bathroom</b>
              <small>Hoà cùng thiên nhiên</small>
            </div>
          </div>
          <div class="card">
            <div class="img" style="height:170px;background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
            <div class="body">
              <b>Hotel Bathroom</b>
              <small>Đồng bộ theo dự án</small>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section" id="blog">
      <div class="container">
        <h2>Kiến thức &amp; Tin tức</h2>
        <p class="section-lead">Cập nhật xu hướng thiết kế, vật liệu, và mẹo bố trí phòng tắm để tối ưu công năng và thẩm mỹ.</p>
        <div class="news">
          <div class="grid-3">
            <div class="card">
              <div class="img" style="background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
              <div class="body">
                <b>Xu hướng thiết kế phòng tắm 2026</b>
                <small>20.03.2026</small>
              </div>
            </div>
            <div class="card">
              <div class="img" style="background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
              <div class="body">
                <b>50 mẫu lavabo đẹp cho phòng tắm</b>
                <small>18.03.2026</small>
              </div>
            </div>
            <div class="card">
              <div class="img" style="background-image:url('/uploads/library/2026/03/250657e450c1d7707c765ef24c2737e0.jpg')"></div>
              <div class="body">
                <b>Ra mắt showroom mới tại Hà Nội</b>
                <small>10.03.2026</small>
              </div>
            </div>
          </div>
          <aside class="contact" id="lien-he">
            <h3>Liên hệ với chúng tôi</h3>
            <div class="line"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>Hotline: 0988 123 456</span></div>
            <div class="line"><i class="fa-solid fa-envelope" aria-hidden="true"></i><span>Email: info@rbconcept.vn</span></div>
            <div class="line"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Địa chỉ: 123 Đường ABC, Quận 1, TP. Hồ Chí Minh</span></div>
          </aside>
        </div>
      </div>
    </section>

    <footer class="site-footer">
      <div class="footer-layer">
        <div class="footer-cta">
          <div class="container">
            <div class="footer-cta-inner">
              <b>Bạn cần tư vấn thiết kế phòng tắm sang trọng?</b>
              <a class="btn btn-ghost" href="/lien-he.php"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Liên hệ tư vấn ngay</a>
            </div>
          </div>
        </div>
        <div class="container footer-main">
          <div class="footer-cols">
            <div class="footer-col">
              <h4>Về chúng tôi</h4>
              <a class="footer-link" href="#">Giới thiệu</a>
              <a class="footer-link" href="#du-an">Dự án</a>
            </div>
            <div class="footer-col">
              <h4>Danh mục</h4>
              <a class="footer-link" href="#san-pham">Bồn tắm</a>
              <a class="footer-link" href="#san-pham">Lavabo</a>
              <a class="footer-link" href="#san-pham">Phụ kiện phòng tắm</a>
            </div>
            <div class="footer-col">
              <h4>Thông tin</h4>
              <a class="footer-link" href="/blog.php">Blog</a>
              <a class="footer-link" href="/blog.php">Tin tức</a>
            </div>
            <div class="footer-col">
              <h4>Liên hệ</h4>
              <div class="footer-contact">
                <div class="item"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>Hotline: 0988 123 456</span></div>
                <div class="item"><i class="fa-solid fa-envelope" aria-hidden="true"></i><span>Email: info@rbconcept.vn</span></div>
                <div class="item"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Địa chỉ: 123 Đường ABC, Quận 1, TP. Hồ Chí Minh</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="footer-bottom">© <?php echo date('Y'); ?> RB Concept. All rights reserved.</div>
      </div>
    </footer>
    <script>
      (function(){
        var toggle = document.querySelector('[data-nav-toggle="1"]');
        var nav = document.querySelector('.navlinks');
        if (!toggle || !nav) return;
        toggle.addEventListener('click', function(){
          nav.classList.toggle('is-open');
        });
        document.addEventListener('click', function(e){
          if (!nav.classList.contains('is-open')) return;
          if (e.target === toggle || toggle.contains(e.target)) return;
          if (e.target === nav || nav.contains(e.target)) return;
          nav.classList.remove('is-open');
        });
      })();
    </script>
  </body>
</html>
