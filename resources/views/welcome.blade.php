<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ANTARA AI Publisher membantu redaksi mengolah berita menjadi draft yang ditinjau manusia sebelum diterbitkan ke WordPress.">
    <meta name="theme-color" content="#f4f3ed">
    <title>{{ config('app.name', 'ANTARA AI Publisher') }} — Alur kerja redaksi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="site-shell" id="top">
        <header class="site-header">
            <a class="brand" href="/" aria-label="ANTARA AI Publisher, beranda">
                <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="brand-copy"><strong>ANTARA</strong><small>AI PUBLISHER</small></span>
            </a>

            <nav class="main-nav" aria-label="Navigasi utama">
                <a href="#alur">Alur kerja</a>
                <a href="#kendali">Kendali redaksi</a>
            </nav>

            <a class="header-cta" href="#alur">
                Kenali sistemnya
                <span aria-hidden="true">↗</span>
            </a>
        </header>

        <main>
            <section class="hero" aria-labelledby="hero-title">
                <div class="hero-copy">
                    <p class="eyebrow"><span class="eyebrow-dot"></span> SISTEM PENERBITAN BERITA</p>
                    <h1 id="hero-title">Berita bergerak cepat. <em>Keputusan tetap di tangan redaksi.</em></h1>
                    <p class="hero-intro">Satu alur untuk mengolah berita ANTARA menjadi draft siap tinjau, meminta persetujuan melalui Telegram, lalu menerbitkannya ke WordPress.</p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="#alur">Lihat alur kerja <span aria-hidden="true">↓</span></a>
                        <span class="approval-note"><span class="check-icon" aria-hidden="true">✓</span> Publikasi menunggu persetujuan</span>
                    </div>
                    <div class="hero-footnote"><span class="footnote-line"></span> Dirancang untuk alur redaksi yang terukur dan dapat ditinjau.</div>
                </div>

                <div class="workflow-card" aria-label="Ilustrasi alur kerja ANTARA AI Publisher">
                    <div class="card-topline">
                        <div>
                            <span class="card-kicker">GAMBARAN ALUR</span>
                            <h2>Dari sumber ke publikasi</h2>
                        </div>
                        <span class="window-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    </div>

                    <div class="story-preview">
                        <div class="story-source"><span class="source-mark">A</span><span>SUMBER BERITA <b>ANTARA</b></span><span class="source-arrow" aria-hidden="true">↗</span></div>
                        <div class="story-lines" aria-hidden="true"><i></i><i></i><i></i></div>
                        <span class="preview-caption">Artikel masuk, lalu diperiksa dan disimpan.</span>
                    </div>

                    <div class="flow-rail" aria-hidden="true"><span></span><span></span><span></span></div>

                    <div class="draft-preview">
                        <div class="draft-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 4.75h9.5L19 9.3v9.95A1.75 1.75 0 0 1 17.25 21h-12A1.75 1.75 0 0 1 3.5 19.25v-12A2.5 2.5 0 0 1 6 4.75Z" stroke="currentColor" stroke-width="1.5"/><path d="M14 5v4.5h4.5M7.5 13h7M7.5 16.5h9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </div>
                        <div class="draft-copy"><span class="card-kicker">DRAFT SIAP DITINJAU</span><strong>Fakta dirapikan, naskah ditulis ulang</strong></div>
                        <span class="ai-pill">AI</span>
                    </div>

                    <div class="approval-preview">
                        <div class="approval-heading"><span class="telegram-mark" aria-hidden="true">➤</span><div><span class="card-kicker">LANGKAH REDAKSI</span><strong>Periksa sebelum terbit</strong></div><span class="pending-pill"><i></i> MENUNGGU</span></div>
                        <div class="approval-buttons"><span>✓ &nbsp; Approve</span><span>× &nbsp; Reject</span></div>
                    </div>

                    <div class="publish-preview"><span class="publish-check" aria-hidden="true">✓</span><span>WORDPRESS</span><b>Terbit setelah disetujui</b><span class="publish-arrow" aria-hidden="true">↗</span></div>
                    <p class="illustration-label">ILUSTRASI ALUR — BUKAN STATUS LANGSUNG</p>
                </div>
            </section>

            <section class="workflow-section" id="alur" aria-labelledby="workflow-title">
                <div class="section-heading">
                    <div><p class="eyebrow">SATU ALUR, EMPAT TAHAP</p><h2 id="workflow-title">Dari berita masuk sampai tayang.</h2></div>
                    <p>Setiap tahap punya tujuan yang jelas. Persetujuan redaksi menjadi gerbang sebelum publikasi.</p>
                </div>

                <div class="steps-grid">
                    <article class="step-card">
                        <span class="step-number">01</span>
                        <div class="step-icon source-step" aria-hidden="true"><span>A</span></div>
                        <h3>Ambil dari ANTARA</h3>
                        <p>Artikel dikumpulkan, divalidasi, dan diperiksa agar tidak diproses dua kali.</p>
                        <span class="step-tag">SUMBER</span>
                    </article>
                    <article class="step-card">
                        <span class="step-number">02</span>
                        <div class="step-icon ai-step" aria-hidden="true"><span>✳</span></div>
                        <h3>Olah dengan AI</h3>
                        <p>Fakta diekstrak dan draft disusun mengikuti aturan gaya yang dapat Anda ubah.</p>
                        <span class="step-tag">PENGOLAHAN</span>
                    </article>
                    <article class="step-card">
                        <span class="step-number">03</span>
                        <div class="step-icon review-step" aria-hidden="true"><span>✓</span></div>
                        <h3>Tinjau di Telegram</h3>
                        <p>Redaksi memeriksa draft dan memilih Approve atau Reject langsung dari pesan.</p>
                        <span class="step-tag">KENDALI REDAKSI</span>
                    </article>
                    <article class="step-card">
                        <span class="step-number">04</span>
                        <div class="step-icon publish-step" aria-hidden="true"><span>↗</span></div>
                        <h3>Terbit ke WordPress</h3>
                        <p>Hanya artikel yang disetujui yang diteruskan ke WordPress untuk dipublikasikan.</p>
                        <span class="step-tag">PUBLIKASI</span>
                    </article>
                </div>
            </section>

            <section class="editorial-section" id="kendali" aria-labelledby="editorial-title">
                <div class="editorial-orbit" aria-hidden="true"><span></span><span></span><span></span><b>EDITORIAL<br>CONTROL</b></div>
                <div class="editorial-copy">
                    <p class="eyebrow eyebrow-light">DIBUAT UNTUK ALUR REDAKSI</p>
                    <h2 id="editorial-title">Otomatis dalam proses.<br><em>Terarah dalam keputusan.</em></h2>
                    <p>AI membantu pekerjaan berulang. Redaksi tetap menilai naskah dan memegang keputusan publikasi.</p>
                </div>
                <div class="editorial-points">
                    <div><span>01</span><p><strong>Satu artikel per proses</strong><small>Alur dibuat bertahap agar hasil mudah ditinjau.</small></p></div>
                    <div><span>02</span><p><strong>Aturan gaya bisa disunting</strong><small>Pedoman editorial berada di file yang dapat Anda ubah.</small></p></div>
                    <div><span>03</span><p><strong>Approval sebelum publikasi</strong><small>Artikel menunggu keputusan Telegram sebelum dikirim ke WordPress.</small></p></div>
                </div>
            </section>

            <section class="closing-note" aria-label="Ringkasan sistem">
                <div class="closing-symbol" aria-hidden="true">A<span>+</span></div>
                <p><strong>ANTARA AI Publisher</strong><br><span>Menghubungkan sumber berita, bantuan AI, tinjauan redaksi, dan WordPress dalam satu alur kerja.</span></p>
                <a href="#top" class="back-to-top">Kembali ke atas <span aria-hidden="true">↑</span></a>
            </section>
        </main>

        <footer class="site-footer"><span>ANTARA AI PUBLISHER</span><span>ALUR PENERBITAN DENGAN KENDALI REDAKSI</span><span>© {{ now()->year }}</span></footer>
    </div>
</body>
</html>
