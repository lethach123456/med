<?php
$Header = <<<'HTML'
<!doctype html>
<html lang="vi"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Top Dental Clinic — Nha khoa chuẩn quốc tế: thăm khám, điều trị, thẩm mỹ răng hàm mặt với đội ngũ bác sĩ giàu kinh nghiệm.">
    <title>Top Dental Clinic | Nha khoa chuẩn quốc tế</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <style>
      :root{
        --bg: #f6f9ff;
        --surface: rgba(255,255,255,0.78);
        --surface-2: rgba(255,255,255,0.92);
        --border: rgba(15,23,42,0.10);
        --text: rgba(15,23,42,0.95);
        --muted: rgba(15,23,42,0.70);
        --soft: rgba(15,23,42,0.06);
        --brand: #10b6b0;
        --brand-2: #3b82f6;
        --ok: #16a34a;
        --danger: #e11d48;
        --shadow: 0 18px 60px rgba(15,23,42,0.16);
        --radius: 16px;
        --radius-sm: 12px;
        --max: 1120px;
      }

      *{ box-sizing: border-box; }
      html, body{ height: 100%; }
      body{
        margin: 0;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
        color: var(--text);
        background:
          radial-gradient(900px 500px at 15% 5%, rgba(59,130,246,0.20), transparent 62%),
          radial-gradient(900px 500px at 85% 10%, rgba(16,182,176,0.18), transparent 62%),
          radial-gradient(1200px 700px at 50% 105%, rgba(16,182,176,0.10), transparent 62%),
          linear-gradient(180deg, #ffffff 0%, var(--bg) 60%, #ffffff 100%);
        overflow-x: hidden;
      }

      a{ color: inherit; text-decoration: none; }
      button, input, select, textarea{ font: inherit; color: inherit; }
      img{ max-width: 100%; display: block; }
      .container{ width: min(100% - 32px, var(--max)); margin: 0 auto; }
      .sr-only{ position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

      .topbar{
        position: sticky;
        top: 0;
        z-index: 50;
        backdrop-filter: blur(14px);
        background: linear-gradient(180deg, rgba(255,255,255,0.82), rgba(255,255,255,0.62));
        border-bottom: 1px solid var(--border);
      }
      .nav{
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 0;
        gap: 16px;
      }
      .brand{
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-width: max-content;
      }
      .logo{
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background:
          radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.7), transparent 60%),
          linear-gradient(135deg, rgba(61,214,208,1), rgba(91,140,255,1));
        box-shadow: 0 16px 40px rgba(61,214,208,0.18);
        position: relative;
      }
      .logo:before{
        content:"";
        position: absolute;
        inset: 10px;
        border-radius: 10px;
        border: 1px solid rgba(15,23,42,0.14);
        background: rgba(255,255,255,0.35);
      }
      .brand strong{ font-weight: 700; letter-spacing: -0.02em; }
      .brand span{ display: block; font-size: 12px; color: var(--muted); margin-top: 2px; }
      .brand-title{ line-height: 1.1; }

      .navlinks{
        display: flex;
        align-items: center;
        gap: 18px;
      }
      .navlinks a{
        font-size: 14px;
        color: var(--muted);
        padding: 10px 10px;
        border-radius: 12px;
        transition: background 160ms ease, color 160ms ease;
      }
      .navlinks a:hover{
        background: var(--soft);
        color: var(--text);
      }

      .nav-cta{
        display: flex;
        gap: 10px;
        align-items: center;
        min-width: max-content;
      }
      .pill{
        display: inline-flex;
        align-items: center;
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
        transition: transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease;
        display: inline-flex;
        gap: 10px;
        align-items: center;
      }
      .btn:active{ transform: translateY(1px); }
      .btn-primary{
        background: linear-gradient(135deg, rgba(61,214,208,1), rgba(91,140,255,1));
        color: #04101b;
        box-shadow: 0 18px 60px rgba(91,140,255,0.18), 0 18px 60px rgba(61,214,208,0.14);
      }
      .btn-primary:hover{ filter: brightness(1.05); }
      .btn-ghost{
        background: var(--surface-2);
        color: var(--text);
        border: 1px solid var(--border);
      }
      .btn-ghost:hover{
        background: rgba(255,255,255,0.98);
        border-color: rgba(15,23,42,0.14);
      }
      .icon{
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
      }

      .menu-btn{
        display: none;
        padding: 10px 12px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface-2);
      }

      .hero{
        padding: 56px 0 24px;
        position: relative;
      }
      .hero-grid{
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 28px;
        align-items: start;
      }
      .badge{
        display: inline-flex;
        align-items: center;
        gap: 10px;
        border: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        padding: 10px 12px;
        border-radius: 999px;
        font-size: 13px;
      }
      .badge b{ color: var(--text); font-weight: 600; }
      .h1{
        margin: 16px 0 10px;
        font-size: clamp(34px, 3.5vw, 50px);
        line-height: 1.06;
        letter-spacing: -0.03em;
      }
      .lead{
        margin: 0;
        color: var(--muted);
        font-size: 16px;
        line-height: 1.7;
        max-width: 60ch;
      }
      .hero-actions{
        display: flex;
        gap: 12px;
        margin-top: 18px;
        flex-wrap: wrap;
        align-items: center;
      }
      .trust{
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 22px;
      }
      .trust-card{
        border-radius: 16px;
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 12px 12px;
      }
      .trust-card strong{
        display: block;
        font-size: 18px;
        letter-spacing: -0.02em;
      }
      .trust-card span{
        color: var(--muted);
        font-size: 12px;
        display: block;
        margin-top: 6px;
      }

      .hero-panel{
        border-radius: 22px;
        border: 1px solid var(--border);
        background:
          radial-gradient(420px 260px at 25% 20%, rgba(59,130,246,0.14), transparent 62%),
          radial-gradient(420px 260px at 75% 70%, rgba(16,182,176,0.14), transparent 62%),
          var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
      }
      .panel-top{
        padding: 18px 18px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
      }
      .panel-title{
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .dotrow{
        display: flex;
        gap: 6px;
      }
      .dot{
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: rgba(15,23,42,0.18);
      }
      .dot:nth-child(1){ background: rgba(225,29,72,0.78); }
      .dot:nth-child(2){ background: rgba(245,158,11,0.80); }
      .dot:nth-child(3){ background: rgba(22,163,74,0.72); }
      .panel-body{
        padding: 18px;
      }
      .photo{
        border-radius: 18px;
        border: 1px solid var(--border);
        background-color: rgba(255,255,255,0.90);
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 260px;
        position: relative;
        overflow: hidden;
      }
      .photo:after{
        content:"";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.78));
        pointer-events: none;
      }
      .panel-stats{
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 12px;
      }
      .stat{
        border-radius: 16px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.84);
        padding: 12px 12px;
      }
      .stat strong{ display:block; font-size: 14px; }
      .stat span{ display:block; color: var(--muted); font-size: 12px; margin-top: 6px; }

      .section{
        padding: 46px 0;
      }
      .section-head{
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 16px;
      }
      .kicker{
        color: var(--brand);
        letter-spacing: 0.14em;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
      }
      .h2{
        margin: 6px 0 0;
        font-size: 26px;
        letter-spacing: -0.02em;
      }
      .sub{
        margin: 0;
        color: var(--muted);
        max-width: 68ch;
        line-height: 1.7;
        font-size: 14px;
      }
      .grid-3{
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
      }
      .card{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 16px;
        position: relative;
        overflow: hidden;
      }
      .card:before{
        content:"";
        position: absolute;
        inset: -80px -60px auto auto;
        width: 240px;
        height: 240px;
        background: radial-gradient(circle at 30% 30%, rgba(16,182,176,0.18), transparent 65%);
        transform: rotate(10deg);
        pointer-events: none;
      }
      .card-img{
        width: 100%;
        height: 150px;
        border-radius: 14px;
        border: 1px solid var(--border);
        object-fit: cover;
        margin-top: 12px;
        margin-bottom: 10px;
        background: rgba(255,255,255,0.9);
      }
      .card h3{
        margin: 10px 0 6px;
        font-size: 16px;
        letter-spacing: -0.01em;
      }
      .card p{
        margin: 0;
        color: var(--muted);
        line-height: 1.7;
        font-size: 14px;
      }
      .chip{
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 999px;
        background: rgba(255,255,255,0.92);
        border: 1px solid var(--border);
        color: var(--muted);
        font-size: 12px;
      }

      .grid-2{
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
      }
      .steps{
        display: grid;
        gap: 12px;
      }
      .step{
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 12px;
        align-items: start;
        padding: 14px 14px;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface);
      }
      .step b{
        width: 36px;
        height: 36px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: rgba(255,255,255,0.96);
        border: 1px solid var(--border);
        color: rgba(15,23,42,0.92);
        font-weight: 700;
      }
      .step h4{ margin: 0; font-size: 14px; }
      .step p{ margin: 6px 0 0; color: var(--muted); font-size: 13px; line-height: 1.7; }

      .split{
        border-radius: 22px;
        border: 1px solid var(--border);
        background: var(--surface);
        overflow: hidden;
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 0;
      }
      .split .left{ padding: 18px; }
      .split .right{
        padding: 18px;
        background:
          radial-gradient(600px 380px at 15% 15%, rgba(16,182,176,0.12), transparent 62%),
          radial-gradient(600px 380px at 85% 75%, rgba(59,130,246,0.14), transparent 62%),
          rgba(255,255,255,0.78);
        border-left: 1px solid var(--border);
      }
      .doctors{
        display: grid;
        gap: 12px;
      }
      .doctor{
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 12px;
        align-items: center;
        padding: 14px;
        border-radius: 16px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.86);
      }
      .avatar{
        width: 54px;
        height: 54px;
        border-radius: 18px;
        background:
          linear-gradient(135deg, rgba(255,255,255,0.45), rgba(255,255,255,0.05)),
          linear-gradient(135deg, rgba(59,130,246,0.45), rgba(16,182,176,0.45));
        border: 1px solid var(--border);
        background-size: cover;
        background-position: center;
      }
      .doctor strong{ display:block; }
      .doctor span{ display:block; margin-top: 4px; color: var(--muted); font-size: 12px; }

      .quotes{
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
      }
      .quote{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 16px;
      }
      .quote p{
        margin: 0;
        color: rgba(15,23,42,0.86);
        line-height: 1.7;
        font-size: 14px;
      }
      .quote .who{
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 12px;
      }
      .small{
        color: var(--muted);
        font-size: 12px;
        line-height: 1.4;
      }
      .stars{
        display: inline-flex;
        gap: 4px;
        color: rgba(16,182,176,0.95);
        margin-bottom: 10px;
      }

      .pricing{
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        align-items: stretch;
      }
      .plan{
        border-radius: 20px;
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 16px;
        display: grid;
        gap: 12px;
      }
      .plan.featured{
        background:
          radial-gradient(640px 280px at 30% 10%, rgba(16,182,176,0.14), transparent 62%),
          radial-gradient(640px 280px at 80% 80%, rgba(59,130,246,0.14), transparent 62%),
          rgba(255,255,255,0.92);
        border-color: rgba(15,23,42,0.14);
        box-shadow: var(--shadow);
      }
      .plan h3{ margin: 0; font-size: 16px; }
      .price{
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -0.03em;
      }
      .plan ul{
        margin: 0;
        padding: 0 0 0 18px;
        color: var(--muted);
        line-height: 1.7;
        font-size: 14px;
      }
      .plan .btn{ width: 100%; justify-content: center; }

      .contact{
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap: 14px;
        align-items: start;
      }
      .form{
        border-radius: 22px;
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 16px;
      }
      .fields{
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
      }
      .field{ display: grid; gap: 6px; }
      label{ font-size: 12px; color: var(--muted); }
      input, select, textarea{
        border-radius: 14px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.92);
        padding: 12px 12px;
        outline: none;
      }
      input:focus, select:focus, textarea:focus{
        border-color: rgba(61,214,208,0.60);
        box-shadow: 0 0 0 4px rgba(61,214,208,0.16);
      }
      textarea{ min-height: 110px; resize: vertical; }
      .form-actions{
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
        margin-top: 12px;
        flex-wrap: wrap;
      }
      .hint{ color: var(--muted); font-size: 12px; line-height: 1.5; }

      .info{
        border-radius: 22px;
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 16px;
        display: grid;
        gap: 12px;
      }
      .info .row{
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 10px;
        align-items: start;
        padding: 12px;
        border-radius: 16px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.82);
      }
      .bubble{
        width: 36px;
        height: 36px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: rgba(255,255,255,0.92);
        border: 1px solid var(--border);
      }

      footer{
        border-top: 1px solid var(--border);
        margin-top: 40px;
        padding: 22px 0;
        color: var(--muted);
        font-size: 13px;
      }
      .footer-grid{
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
      }
      .footer-links{
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
      }
      .footer-links a{ padding: 8px 10px; border-radius: 12px; }
      .footer-links a:hover{ background: rgba(255,255,255,0.06); color: var(--text); }

      .toast{
        position: fixed;
        left: 50%;
        bottom: 18px;
        transform: translateX(-50%) translateY(20px);
        opacity: 0;
        pointer-events: none;
        transition: opacity 200ms ease, transform 200ms ease;
        background: rgba(255,255,255,0.86);
        border: 1px solid var(--border);
        backdrop-filter: blur(16px);
        padding: 12px 12px;
        border-radius: 16px;
        box-shadow: var(--shadow);
        width: min(540px, calc(100% - 32px));
        display: flex;
        gap: 10px;
        align-items: flex-start;
      }
      .toast.show{
        opacity: 1;
        transform: translateX(-50%) translateY(0);
      }
      .toast .mark{
        width: 34px;
        height: 34px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: rgba(22,163,74,0.12);
        border: 1px solid rgba(22,163,74,0.22);
        color: rgba(22,163,74,0.95);
        flex: 0 0 auto;
      }
      .toast .danger{
        background: rgba(225,29,72,0.10);
        border-color: rgba(225,29,72,0.22);
        color: rgba(225,29,72,0.95);
      }
      .toast strong{ display:block; font-size: 13px; }
      .toast span{ display:block; margin-top: 4px; color: var(--muted); font-size: 12px; line-height: 1.5; }

      .mobile-drawer{
        display: none;
        position: fixed;
        inset: 70px 16px auto 16px;
        z-index: 60;
        border-radius: 18px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.88);
        backdrop-filter: blur(16px);
        box-shadow: var(--shadow);
        padding: 10px;
      }
      .mobile-drawer a{
        display: flex;
        padding: 12px 12px;
        border-radius: 14px;
        color: var(--muted);
        transition: background 160ms ease, color 160ms ease;
      }
      .mobile-drawer a:hover{ background: rgba(255,255,255,0.06); color: var(--text); }
      .mobile-drawer .drawer-cta{
        display: grid;
        gap: 10px;
        padding: 10px;
      }

      @media (max-width: 920px){
        .hero-grid{ grid-template-columns: 1fr; }
        .grid-3{ grid-template-columns: 1fr; }
        .grid-2{ grid-template-columns: 1fr; }
        .quotes{ grid-template-columns: 1fr; }
        .pricing{ grid-template-columns: 1fr; }
        .split{ grid-template-columns: 1fr; }
        .split .right{ border-left: 0; border-top: 1px solid rgba(255,255,255,0.10); }
        .contact{ grid-template-columns: 1fr; }
        .trust{ grid-template-columns: 1fr; }
      }

      @media (max-width: 860px){
        .navlinks{ display: none; }
        .pill{ display: none; }
        .menu-btn{ display: inline-flex; align-items: center; gap: 8px; }
        .mobile-drawer.show{ display: block; }
      }

      @media (max-width: 600px){
        .container{ width: min(100% - 24px, var(--max)); }
        .nav{ padding: 12px 0; gap: 12px; }
        .logo{ width: 36px; height: 36px; border-radius: 12px; }
        .logo:before{ inset: 9px; }
        .brand strong{ font-size: 14px; }
        .brand span{ display: none; }
        .brand{ min-width: 0; }
        .brand-title{ max-width: 48vw; }
        .nav-cta{ min-width: 0; }
        .nav-cta .btn-primary{ display: none; }
        .menu-btn{ padding: 10px 10px; }

        .hero{ padding: 34px 0 16px; }
        .h1{ font-size: clamp(30px, 7.2vw, 40px); }
        .lead{ font-size: 15px; }
        .badge{ padding: 9px 10px; gap: 8px; }

        .hero-actions{ gap: 10px; }
        .hero-actions .btn{ flex: 1 1 100%; justify-content: center; }
        .hero-actions .chip{ width: 100%; justify-content: center; }

        .panel-top{ flex-direction: column; align-items: flex-start; padding: 14px 14px 0; gap: 10px; }
        .panel-body{ padding: 14px; }
        .photo{ min-height: 200px; }
        .panel-stats{ grid-template-columns: 1fr; }

        .section{ padding: 34px 0; }
        .section-head{ flex-direction: column; align-items: flex-start; gap: 10px; }
        .h2{ font-size: 22px; }
        .sub{ font-size: 13px; }

        .card{ padding: 14px; }
        .card-img{ height: 140px; }
        .step{ padding: 12px; }
        .plan{ padding: 14px; }

        .fields{ grid-template-columns: 1fr; }
        .form-actions{ flex-direction: column; align-items: stretch; }
        .form-actions .btn{ width: 100%; }
      }

      @media (max-width: 420px){
        .container{ width: min(100% - 20px, var(--max)); }
        .hero{ padding: 28px 0 14px; }
        .h1{ font-size: 28px; }
        .photo{ min-height: 180px; }
        .mobile-drawer{ inset: 66px 12px auto 12px; }
        .menu-label{ display: none; }
      }
      @media (prefers-reduced-motion: reduce){
        *{ transition: none !important; scroll-behavior: auto !important; }
      }
      html{ scroll-behavior: smooth; }
    </style>
  </head>
  <body>
    <header class="topbar">
      <div class="container">
        <nav class="nav" aria-label="Điều hướng chính">
          <a class="brand" href="#top" aria-label="Top Dental Clinic">
            <div class="logo" aria-hidden="true"></div>
            <div class="brand-title">
              <strong>Top Dental Clinic</strong>
              <span>Nha khoa chuẩn quốc tế</span>
            </div>
          </a>

          <div class="navlinks" role="navigation" aria-label="Liên kết nhanh">
            <a href="#dich-vu">Dịch vụ</a>
            <a href="#quy-trinh">Quy trình</a>
            <a href="#bac-si">Bác sĩ</a>
            <a href="#bang-gia">Bảng giá</a>
            <a href="#lien-he">Liên hệ</a>
          </div>

          <div class="nav-cta">
            <div class="pill" aria-label="Thông tin liên hệ nhanh">
              <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M7.5 3.5h9A2.5 2.5 0 0 1 19 6v12a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 18V6a2.5 2.5 0 0 1 2.5-2.5Z" stroke="currentColor" stroke-width="1.6"></path>
                <path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
              </svg>
              <span>Hotline: <b style="color: var(--text); font-weight: 600;" class="">1900 6868</b></span>
            </div>
            <button class="btn btn-ghost menu-btn" type="button" id="menuBtn" aria-expanded="false" aria-controls="mobileDrawer">
              <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
              </svg>
              <span class="menu-label">Menu</span>
            </button>
            <a class="btn btn-primary" href="#lien-he">
              <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M7 12.5 10.2 16 17.5 8.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M12 22c5.5 0 10-4.5 10-10S17.5 2 12 2 2 6.5 2 12s4.5 10 10 10Z" stroke="currentColor" stroke-width="1.2" opacity="0.55"></path>
              </svg>
              Đặt lịch ngay
            </a>
          </div>
        </nav>
      </div>
    </header>

    <div class="mobile-drawer" id="mobileDrawer" aria-label="Menu di động">
      <a href="#dich-vu">Dịch vụ</a>
      <a href="#quy-trinh">Quy trình</a>
      <a href="#bac-si">Bác sĩ</a>
      <a href="#bang-gia">Bảng giá</a>
      <a href="#lien-he">Liên hệ</a>
      <div class="drawer-cta">
        <a class="btn btn-primary" href="#lien-he" style="justify-content:center;">Đặt lịch ngay</a>
        <a class="btn btn-ghost" href="tel:19006868" style="justify-content:center;">Gọi 1900 6868</a>
      </div>
    </div>
HTML;

$Home = <<<'HTML'
    <main id="top">
      <section class="hero">
        <div class="container">
          <div class="hero-grid">
            <div class="">
              <div class="badge">
                <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 2 3 6v6c0 6 4 9.8 9 10 5-.2 9-4 9-10V6l-9-4Z" stroke="currentColor" stroke-width="1.4" opacity="0.72"></path>
                  <path d="M8 12.2 10.8 15 16.5 9.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                Chuẩn hoá vô trùng <b>5 bước</b> · Bác sĩ <b>chuyên khoa</b> · Bảo hành <b>minh bạch</b>
              </div>
              <h1 class="h1">Nụ cười tự tin, dịch vụ nha khoa chuyên nghiệp cho gia đình bạn</h1>
              <p class="lead">
                Top Dental Clinic cung cấp thăm khám tổng quát, điều trị chuyên sâu và thẩm mỹ răng với quy trình chuẩn,
                thiết bị hiện đại, trải nghiệm nhẹ nhàng.
              </p>
              <div class="hero-actions">
                <a class="btn btn-primary" href="#lien-he">
                  <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M7.5 12h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                    <path d="M12 7.5V16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                    <path d="M12 22c5.5 0 10-4.5 10-10S17.5 2 12 2 2 6.5 2 12s4.5 10 10 10Z" stroke="currentColor" stroke-width="1.2" opacity="0.55"></path>
                  </svg>
                  Nhận tư vấn miễn phí
                </a>
                <a class="btn btn-ghost" href="#dich-vu">
                  <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 4v16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" opacity="0.85"></path>
                    <path d="M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                  </svg>
                  Xem dịch vụ
                </a>
                <span class="chip" aria-label="Đánh giá tổng quan">
                  <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 17.3 6.7 20l1-5.9L3.3 9.9l6-.9L12 3.6 14.7 9l6 .9-4.4 4.2 1 5.9L12 17.3Z" fill="currentColor" opacity="0.85"></path>
                  </svg>
                  4.9/5 · 2.300+ khách hàng
                </span>
              </div>

              <div class="trust" aria-label="Thống kê nổi bật">
                <div class="trust-card">
                  <strong class="">10+ năm</strong>
                  <span>Kinh nghiệm điều trị</span>
                </div>
                <div class="trust-card">
                  <strong>20.000+</strong>
                  <span>Ca chăm sóc nụ cười</span>
                </div>
                <div class="trust-card">
                  <strong>24/7</strong>
                  <span>Hỗ trợ tư vấn</span>
                </div>
              </div>
            </div>

            <aside class="hero-panel" aria-label="Tổng quan nhanh">
              <div class="panel-top">
                <div class="panel-title">
                  <div class="dotrow" aria-hidden="true">
                    <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                  </div>
                  <span class="small">Phòng khám · Lịch hẹn · Quy trình</span>
                </div>
                <span class="chip">Mở cửa: 08:00–20:00</span>
              </div>
              <div class="panel-body">
                <div class="photo" aria-label="Không gian phòng khám" style="background-image: url(&quot;https://media.istockphoto.com/id/1388931724/vi/anh/h%C3%ACnh-%E1%BA%A3nh-m%E1%BB%99t-ph%E1%BB%A5-n%E1%BB%AF-tr%E1%BA%BB-ki%E1%BB%83m-tra-k%E1%BA%BFt-qu%E1%BA%A3-c%E1%BB%A7a-m%C3%ACnh-trong-v%C4%83n-ph%C3%B2ng-nha-s%C4%A9.jpg?s=612x612&amp;w=0&amp;k=20&amp;c=jCnjakg27CnEF0kUN5-1Iq-4DYwEg58oO5EqMxDCEoU=&quot;); background-size: cover; background-position: center center; background-repeat: no-repeat;"></div>
                <div class="panel-stats">
                  <div class="stat">
                    <strong>Khám &amp; tư vấn</strong>
                    <span>Định hướng phác đồ rõ ràng, cá nhân hoá.</span>
                  </div>
                  <div class="stat">
                    <strong class="">Chụp phim tại chỗ</strong>
                    <span>X-ray/CT hỗ trợ chẩn đoán nhanh, chính xác.</span>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </div>
      </section>

      <section class="section" id="dich-vu">
        <div class="container">
          <div class="section-head">
            <div>
              <div class="kicker">Dịch vụ</div>
              <h2 class="h2">Trọn gói điều trị &amp; thẩm mỹ răng</h2>
            </div>
            <p class="sub">Tối ưu trải nghiệm: nhẹ nhàng, rõ ràng chi phí, minh bạch vật liệu và lộ trình điều trị.</p>
          </div>

          <div class="grid-3">
            <div class="card">
              <span class="chip">
                <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 3 4 7v10l8 4 8-4V7l-8-4Z" stroke="currentColor" stroke-width="1.4" opacity="0.75"></path>
                  <path d="M8.5 12h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                  <path d="M12 8.5V15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                Tổng quát
              </span>
              <img class="card-img" src="https://media.istockphoto.com/id/1388931724/vi/anh/h%C3%ACnh-%E1%BA%A3nh-m%E1%BB%99t-ph%E1%BB%A5-n%E1%BB%AF-tr%E1%BA%BB-ki%E1%BB%83m-tra-k%E1%BA%BFt-qu%E1%BA%A3-c%E1%BB%A7a-m%C3%ACnh-trong-v%C4%83n-ph%C3%B2ng-nha-s%C4%A9.jpg?s=612x612&amp;w=0&amp;k=20&amp;c=jCnjakg27CnEF0kUN5-1Iq-4DYwEg58oO5EqMxDCEoU=" alt="Khám răng tổng quát tại phòng khám" loading="lazy">
              <h3>Khám răng tổng quát &amp; điều trị sâu răng</h3>
              <p>Chẩn đoán sớm, điều trị triệt để: trám răng, điều trị tuỷ, nhổ răng khôn an toàn.</p>
            </div>
            <div class="card">
              <span class="chip">
                <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 21c6-1 7-6.2 7-10 0-4-2.8-7-7-7s-7 3-7 7c0 3.8 1 9 7 10Z" stroke="currentColor" stroke-width="1.5" opacity="0.75"></path>
                  <path d="M8.5 11.5h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                Thẩm mỹ
              </span>
              <img class="card-img" src="https://media.istockphoto.com/id/1388931724/vi/anh/h%C3%ACnh-%E1%BA%A3nh-m%E1%BB%99t-ph%E1%BB%A5-n%E1%BB%AF-tr%E1%BA%BB-ki%E1%BB%83m-tra-k%E1%BA%BFt-qu%E1%BA%A3-c%E1%BB%A7a-m%C3%ACnh-trong-v%C4%83n-ph%C3%B2ng-nha-s%C4%A9.jpg?s=612x612&amp;w=0&amp;k=20&amp;c=jCnjakg27CnEF0kUN5-1Iq-4DYwEg58oO5EqMxDCEoU=" alt="Dịch vụ thẩm mỹ nụ cười" loading="lazy">
              <h3>Tẩy trắng, dán sứ Veneer, bọc sứ</h3>
              <p>Thiết kế nụ cười hài hoà khuôn mặt, vật liệu chính hãng, bảo hành theo từng hạng mục.</p>
            </div>
            <div class="card">
              <span class="chip">
                <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 4c4.5 0 8 3.5 8 8s-3.5 8-8 8-8-3.5-8-8 3.5-8 8-8Z" stroke="currentColor" stroke-width="1.4" opacity="0.75"></path>
                  <path d="M9 12h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                  <path d="M12 9v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                Chuyên sâu
              </span>
              <img class="card-img" src="https://media.istockphoto.com/id/1388931724/vi/anh/h%C3%ACnh-%E1%BA%A3nh-m%E1%BB%99t-ph%E1%BB%A5-n%E1%BB%AF-tr%E1%BA%BB-ki%E1%BB%83m-tra-k%E1%BA%BFt-qu%E1%BA%A3-c%E1%BB%A7a-m%C3%ACnh-trong-v%C4%83n-ph%C3%B2ng-nha-s%C4%A9.jpg?s=612x612&amp;w=0&amp;k=20&amp;c=jCnjakg27CnEF0kUN5-1Iq-4DYwEg58oO5EqMxDCEoU=" alt="Niềng răng và điều trị chuyên sâu" loading="lazy">
              <h3>Niềng răng &amp; cấy ghép Implant</h3>
              <p class="">Lập kế hoạch điều trị số hoá, theo dõi định kỳ, đội ngũ chuyên khoa chỉnh nha và Implant.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="section" id="quy-trinh">
        <div class="container">
          <div class="section-head">
            <div>
              <div class="kicker">Quy trình</div>
              <h2 class="h2">Điều trị rõ ràng, an tâm từng bước</h2>
            </div>
            <p class="sub">Từ thăm khám đến bảo hành, mọi thông tin đều được giải thích dễ hiểu và có hồ sơ lưu trữ.</p>
          </div>

          <div class="grid-2">
            <div class="steps" aria-label="Các bước tiêu chuẩn">
              <div class="step">
                <b>1</b>
                <div>
                  <h4>Tiếp nhận &amp; đánh giá</h4>
                  <p>Kiểm tra tổng quát, chụp phim khi cần, lắng nghe nhu cầu và tiền sử.</p>
                </div>
              </div>
              <div class="step">
                <b>2</b>
                <div>
                  <h4>Lập phác đồ cá nhân hoá</h4>
                  <p>Giải thích phương án, thời gian, chi phí, vật liệu; thống nhất trước khi điều trị.</p>
                </div>
              </div>
              <div class="step">
                <b>3</b>
                <div>
                  <h4>Điều trị &amp; theo dõi</h4>
                  <p>Vô trùng chuẩn, giảm đau tối ưu, nhắc lịch tái khám và hướng dẫn chăm sóc.</p>
                </div>
              </div>
              <div class="step">
                <b>4</b>
                <div>
                  <h4>Hồ sơ &amp; bảo hành</h4>
                  <p>Lưu hồ sơ điều trị, hẹn tái khám định kỳ, bảo hành minh bạch theo hạng mục.</p>
                </div>
              </div>
            </div>

            <div class="split" aria-label="Cam kết chất lượng">
              <div class="left">
                <span class="chip">Cam kết</span>
                <h3 style="margin: 12px 0 8px; font-size: 18px; letter-spacing: -0.02em;">Chuẩn vô trùng · Kỹ thuật số · Minh bạch vật liệu</h3>
                <p class="sub" style="margin-top: 0;">
                  Thiết bị hỗ trợ chẩn đoán và điều trị giúp tối ưu thời gian, giảm khó chịu. Chi phí và vật liệu được thông báo rõ ràng.
                </p>
                <div style="display:grid; gap:10px; margin-top: 14px;">
                  <span class="chip">Tiêu chuẩn vô trùng nhiều lớp</span>
                  <span class="chip">Theo dõi sau điều trị qua nhắc lịch</span>
                  <span class="chip">Tư vấn rõ ràng trước khi làm</span>
                </div>
              </div>
              <div class="right">
                <div class="doctors">
                  <div class="doctor">
                    <div class="avatar" aria-hidden="true" style="background-image: linear-gradient(135deg, rgba(255,255,255,0.45), rgba(255,255,255,0.05)), url('https://source.unsplash.com/240x240/?dentist,doctor,portrait');"></div>
                    <div>
                      <strong>BS. Nguyễn Minh An</strong>
                      <span>Chuyên khoa Implant · 12 năm kinh nghiệm</span>
                    </div>
                  </div>
                  <div class="doctor">
                    <div class="avatar" aria-hidden="true" style="background-image: linear-gradient(135deg, rgba(255,255,255,0.45), rgba(255,255,255,0.05)), url('https://source.unsplash.com/240x240/?female,doctor,portrait');"></div>
                    <div>
                      <strong>BS. Trần Thuỳ Linh</strong>
                      <span>Chỉnh nha · Theo dõi điều trị số hoá</span>
                    </div>
                  </div>
                  <div class="doctor">
                    <div class="avatar" aria-hidden="true" style="background-image: linear-gradient(135deg, rgba(255,255,255,0.45), rgba(255,255,255,0.05)), url('https://source.unsplash.com/240x240/?surgeon,doctor,portrait');"></div>
                    <div>
                      <strong>BS. Phạm Quốc Huy</strong>
                      <span>Thẩm mỹ răng · Thiết kế nụ cười</span>
                    </div>
                  </div>
                </div>
                <div style="margin-top: 12px; display:flex; gap:10px; flex-wrap: wrap;">
                  <a class="btn btn-primary" href="#lien-he" style="flex: 1; justify-content:center; min-width: 180px;">Đặt lịch với bác sĩ</a>
                  <a class="btn btn-ghost" href="#bang-gia" style="flex: 1; justify-content:center; min-width: 180px;">Xem bảng giá</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="section" id="bac-si">
        <div class="container">
          <div class="section-head">
            <div>
              <div class="kicker">Đánh giá</div>
              <h2 class="h2">Khách hàng nói gì về Top Dental Clinic</h2>
            </div>
            <p class="sub">Trải nghiệm được ưu tiên: tư vấn dễ hiểu, quy trình nhẹ nhàng, kết quả bền vững.</p>
          </div>

          <div class="quotes">
            <article class="quote" aria-label="Đánh giá 1">
              <div class="stars" aria-hidden="true">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
              </div>
              <p>“Bác sĩ tư vấn rất rõ ràng, làm răng sứ xong nụ cười tự nhiên. Quy trình sạch và chuyên nghiệp.”</p>
              <div class="who">
                <div class="avatar" aria-hidden="true" style="width:40px;height:40px;border-radius:14px;background-image: linear-gradient(135deg, rgba(255,255,255,0.45), rgba(255,255,255,0.05)), url('https://source.unsplash.com/160x160/?woman,portrait');"></div>
                <div>
                  <strong style="font-size: 13px;">Chị Mai Anh</strong>
                  <div class="small">Thẩm mỹ răng sứ</div>
                </div>
              </div>
            </article>
            <article class="quote" aria-label="Đánh giá 2">
              <div class="stars" aria-hidden="true">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
              </div>
              <p>“Nhổ răng khôn nhẹ nhàng hơn mình tưởng, chăm sóc sau điều trị tốt và có nhắc lịch tái khám.”</p>
              <div class="who">
                <div class="avatar" aria-hidden="true" style="width:40px;height:40px;border-radius:14px;background-image: linear-gradient(135deg, rgba(255,255,255,0.45), rgba(255,255,255,0.05)), url('https://source.unsplash.com/160x160/?man,portrait');"></div>
                <div>
                  <strong style="font-size: 13px;">Anh Quốc Thịnh</strong>
                  <div class="small">Tiểu phẫu răng khôn</div>
                </div>
              </div>
            </article>
            <article class="quote" aria-label="Đánh giá 3">
              <div class="stars" aria-hidden="true">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
              </div>
              <p>“Niềng răng có kế hoạch rõ, bác sĩ theo dõi sát. Phòng khám hiện đại, nhân viên nhiệt tình.”</p>
              <div class="who">
                <div class="avatar" aria-hidden="true" style="width:40px;height:40px;border-radius:14px;background-image: linear-gradient(135deg, rgba(255,255,255,0.45), rgba(255,255,255,0.05)), url('https://source.unsplash.com/160x160/?girl,portrait');"></div>
                <div>
                  <strong style="font-size: 13px;">Bạn Khánh Linh</strong>
                  <div class="small">Chỉnh nha</div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section class="section" id="bang-gia">
        <div class="container">
          <div class="section-head">
            <div>
              <div class="kicker">Bảng giá</div>
              <h2 class="h2">Gói dịch vụ minh bạch, dễ lựa chọn</h2>
            </div>
            <p class="sub">Giá tham khảo theo gói. Khi thăm khám, bác sĩ sẽ tư vấn phác đồ phù hợp và báo giá chi tiết.</p>
          </div>

          <div class="pricing" aria-label="Các gói dịch vụ">
            <div class="plan">
              <div>
                <span class="chip">Cơ bản</span>
                <h3 style="margin-top: 10px;">Chăm sóc định kỳ</h3>
              </div>
              <div>
                <div class="price">399k</div>
                <div class="small">/ lần thăm khám</div>
              </div>
              <ul>
                <li>Khám tổng quát</li>
                <li>Lấy cao răng</li>
                <li>Đánh bóng</li>
                <li>Tư vấn chăm sóc tại nhà</li>
              </ul>
              <a class="btn btn-ghost" href="#lien-he">Đăng ký gói</a>
            </div>
            <div class="plan featured">
              <div>
                <span class="chip">Phổ biến</span>
                <h3 style="margin-top: 10px;">Thẩm mỹ nụ cười</h3>
              </div>
              <div>
                <div class="price">Từ 2.9tr</div>
                <div class="small">/ hạng mục</div>
              </div>
              <ul>
                <li>Tẩy trắng răng</li>
                <li>Dán sứ Veneer</li>
                <li>Bọc sứ thẩm mỹ</li>
                <li>Bảo hành theo vật liệu</li>
              </ul>
              <a class="btn btn-primary" href="#lien-he">Nhận tư vấn</a>
            </div>
            <div class="plan">
              <div>
                <span class="chip">Chuyên sâu</span>
                <h3 style="margin-top: 10px;">Chỉnh nha &amp; Implant</h3>
              </div>
              <div>
                <div class="price">Từ 18tr</div>
                <div class="small">/ liệu trình</div>
              </div>
              <ul>
                <li>Niềng răng mắc cài/khay trong</li>
                <li>Lập kế hoạch điều trị số hoá</li>
                <li>Cấy ghép Implant</li>
                <li>Theo dõi định kỳ</li>
              </ul>
              <a class="btn btn-ghost" href="#lien-he">Đặt lịch tư vấn</a>
            </div>
          </div>
        </div>
      </section>

      <section class="section" id="lien-he">
        <div class="container">
          <div class="section-head">
            <div>
              <div class="kicker">Liên hệ</div>
              <h2 class="h2">Đặt lịch hẹn nhanh</h2>
            </div>
            <p class="sub">Điền thông tin để được tư vấn. Bạn cũng có thể gọi hotline để được hỗ trợ ngay.</p>
          </div>

          <div class="contact">
            <form class="form" id="bookingForm" novalidate="">
              <div class="fields">
                <div class="field">
                  <label for="fullName">Họ và tên</label>
                  <input id="fullName" name="fullName" autocomplete="name" required="" placeholder="VD: Nguyễn Văn A">
                </div>
                <div class="field">
                  <label for="phone">Số điện thoại</label>
                  <input id="phone" name="phone" inputmode="tel" autocomplete="tel" required="" placeholder="VD: 0901 234 567">
                </div>
                <div class="field">
                  <label for="service">Dịch vụ quan tâm</label>
                  <select id="service" name="service" required="">
                    <option value="" selected="">Chọn dịch vụ</option>
                    <option>Khám tổng quát</option>
                    <option>Nhổ răng khôn</option>
                    <option>Điều trị tuỷ</option>
                    <option>Tẩy trắng răng</option>
                    <option>Răng sứ/Veneer</option>
                    <option>Niềng răng</option>
                    <option>Implant</option>
                  </select>
                </div>
                <div class="field">
                  <label for="time">Thời gian mong muốn</label>
                  <select id="time" name="time" required="">
                    <option value="" selected="">Chọn khung giờ</option>
                    <option>Sáng (08:00–11:30)</option>
                    <option>Chiều (13:30–17:30)</option>
                    <option>Tối (18:00–20:00)</option>
                  </select>
                </div>
              </div>
              <div class="field" style="margin-top: 12px;">
                <label for="note">Ghi chú (tuỳ chọn)</label>
                <textarea id="note" name="note" placeholder="VD: Đau răng hàm dưới bên phải, muốn khám sớm"></textarea>
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" type="submit" style="min-width: 200px; justify-content:center;">
                  <svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M20 7 9 18l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                  </svg>
                  Gửi yêu cầu
                </button>
                <div class="hint">Bằng việc gửi, bạn đồng ý để Top Dental Clinic liên hệ tư vấn.</div>
              </div>
            </form>

            <aside class="info" aria-label="Thông tin phòng khám">
              <div class="row">
                <div class="bubble" aria-hidden="true">
                  <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M12 22s8-5 8-12a8 8 0 1 0-16 0c0 7 8 12 8 12Z" stroke="currentColor" stroke-width="1.4" opacity="0.75"></path>
                    <path d="M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" fill="currentColor" opacity="0.75"></path>
                  </svg>
                </div>
                <div>
                  <strong>Địa chỉ</strong>
                  <div class="small">123 Đường Sức Khoẻ, P. Nụ Cười, Q. 1, TP.HCM</div>
                </div>
              </div>
              <div class="row">
                <div class="bubble" aria-hidden="true">
                  <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M6.6 10.8c1.9 3.6 3 4.7 6.6 6.6l2.1-2.1c.4-.4 1-.5 1.5-.3l2.6 1a1.2 1.2 0 0 1 .7 1.3c-.4 2-2.1 3.7-4.4 3.7C9.6 21 3 14.4 3 7.8 3 5.5 4.7 3.8 6.7 3.4c.6-.1 1.2.2 1.4.7l1 2.6c.2.5.1 1.1-.3 1.5L6.6 10.8Z" stroke="currentColor" stroke-width="1.4" opacity="0.75"></path>
                  </svg>
                </div>
                <div>
                  <strong>Hotline</strong>
                  <div class="small"><a href="tel:19006868" style="color: var(--text);">1900 6868</a> · Hỗ trợ 24/7</div>
                </div>
              </div>
              <div class="row">
                <div class="bubble" aria-hidden="true">
                  <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M12 8v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M12 22c5.5 0 10-4.5 10-10S17.5 2 12 2 2 6.5 2 12s4.5 10 10 10Z" stroke="currentColor" stroke-width="1.4" opacity="0.75"></path>
                  </svg>
                </div>
                <div>
                  <strong class="">Giờ làm việc</strong>
                  <div class="small">Thứ 2–CN: 08:00–20:00</div>
                </div>
              </div>
              <div class="row">
                <div class="bubble" aria-hidden="true">
                  <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" opacity="0.75"></path>
                  </svg>
                </div>
                <div>
                  <strong>Dịch vụ phổ biến</strong>
                  <div class="small">Khám tổng quát · Thẩm mỹ răng sứ · Niềng răng · Implant</div>
                </div>
              </div>
              <a class="btn btn-ghost" href="#dich-vu" style="justify-content:center;">Xem toàn bộ dịch vụ</a>
            </aside>
          </div>
        </div>
      </section>
    </main>
HTML;

$Footer = <<<'HTML'
    <footer>
      <div class="container">
        <div class="footer-grid">
          <div>
            <strong style="color: var(--text);">Top Dental Clinic</strong>
            <div class="small">© <span id="year">2026</span> Top Dental Clinic. All rights reserved.</div>
          </div>
          <div class="footer-links" aria-label="Liên kết chân trang">
            <a href="#dich-vu">Dịch vụ</a>
            <a href="#bang-gia">Bảng giá</a>
            <a href="#lien-he">Liên hệ</a>
            <a href="#top">Lên đầu trang</a>
          </div>
        </div>
      </div>
    </footer>

    <div class="toast" id="toast" role="status" aria-live="polite" aria-atomic="true">
      <div class="mark" id="toastMark" aria-hidden="true">
        <svg class="icon" viewBox="0 0 24 24" fill="none">
          <path d="M20 7 9 18l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </div>
      <div>
        <strong id="toastTitle">Đã gửi yêu cầu</strong>
        <span id="toastMsg">Chúng tôi sẽ liên hệ bạn trong thời gian sớm nhất.</span>
      </div>
    </div>

    <script>
      const qs = (s, el = document) => el.querySelector(s);
      const qsa = (s, el = document) => Array.from(el.querySelectorAll(s));

      const drawer = qs("#mobileDrawer");
      const menuBtn = qs("#menuBtn");
      const yearEl = qs("#year");
      yearEl.textContent = String(new Date().getFullYear());

      function setDrawer(open) {
        const isOpen = Boolean(open);
        drawer.classList.toggle("show", isOpen);
        menuBtn.setAttribute("aria-expanded", String(isOpen));
      }

      menuBtn?.addEventListener("click", () => {
        const open = !drawer.classList.contains("show");
        setDrawer(open);
      });

      qsa('a[href^="#"]').forEach((a) => {
        a.addEventListener("click", () => {
          setDrawer(false);
        });
      });

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") setDrawer(false);
      });

      const toast = qs("#toast");
      const toastTitle = qs("#toastTitle");
      const toastMsg = qs("#toastMsg");
      const toastMark = qs("#toastMark");
      let toastTimer = 0;

      function showToast({ title, message, type }) {
        window.clearTimeout(toastTimer);
        toastTitle.textContent = title;
        toastMsg.textContent = message;
        toastMark.classList.toggle("danger", type === "danger");
        toast.classList.add("show");
        toastTimer = window.setTimeout(() => toast.classList.remove("show"), 3400);
      }

      const form = qs("#bookingForm");

      function normalizePhone(raw) {
        return String(raw).replace(/[^\d+]/g, "");
      }

      function isValidPhone(phone) {
        const p = normalizePhone(phone);
        if (p.startsWith("+84")) return p.length >= 11 && p.length <= 13;
        if (p.startsWith("0")) return p.length >= 9 && p.length <= 11;
        return p.length >= 9 && p.length <= 13;
      }

      form?.addEventListener("submit", (e) => {
        e.preventDefault();
        const data = new FormData(form);
        const fullName = String(data.get("fullName") || "").trim();
        const phone = String(data.get("phone") || "").trim();
        const service = String(data.get("service") || "").trim();
        const time = String(data.get("time") || "").trim();

        if (!fullName || fullName.length < 2) {
          showToast({ title: "Thiếu thông tin", message: "Vui lòng nhập họ và tên.", type: "danger" });
          qs("#fullName")?.focus();
          return;
        }
        if (!phone || !isValidPhone(phone)) {
          showToast({ title: "Số điện thoại chưa đúng", message: "Vui lòng kiểm tra và nhập lại số điện thoại.", type: "danger" });
          qs("#phone")?.focus();
          return;
        }
        if (!service) {
          showToast({ title: "Chưa chọn dịch vụ", message: "Vui lòng chọn dịch vụ bạn quan tâm.", type: "danger" });
          qs("#service")?.focus();
          return;
        }
        if (!time) {
          showToast({ title: "Chưa chọn thời gian", message: "Vui lòng chọn khung giờ bạn mong muốn.", type: "danger" });
          qs("#time")?.focus();
          return;
        }

        form.reset();
        showToast({ title: "Đã gửi yêu cầu", message: "Top Dental Clinic sẽ liên hệ bạn trong thời gian sớm nhất.", type: "ok" });
      });
    </script>
  

</body></html>
HTML;

$Template = $Header . $Home . $Footer;
