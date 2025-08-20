<?php
require_once "connect.php";
session_start();


// Directory where profile pictures are stored
$PROFILE_DIR = "profile_pics/";

/* ----------------- HELPERS ----------------- */

/**
 * Escape string for safe HTML output (prevents XSS).
 */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate initials (1–2 letters) from full name.
 * Example: "Phone Thaw Naing" → "PT"
 */
function initials($name)
{
    $parts = preg_split('/\s+/', trim((string)$name));
    $ini = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            // Take first character of each part, uppercase
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        // Stop after 2 letters
        if (mb_strlen($ini) >= 2) break;
    }
    // If no name, return default "YN"
    return $ini ?: 'YN';
}

/**
 * Convert a local image file to a base64 data URI.
 * Returns '' if file is missing/invalid.
 */
function data_uri_for_file($fs_path)
{
    // If file not readable, stop
    if (!is_readable($fs_path)) return '';

    // Detect mime type
    $mime = function_exists('mime_content_type') ? @mime_content_type($fs_path) : '';
    if (!$mime) {
        // Fallback: guess mime type from extension
        $ext = strtolower(pathinfo($fs_path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mime = $map[$ext] ?? '';
    }
    if (!$mime) return '';

    // Read file contents
    $data = @file_get_contents($fs_path);
    if ($data === false) return '';

    // Return base64-encoded data URI
    return "data:$mime;base64," . base64_encode($data);
}

/* ----------------- AUTH & PREMIUM CHECK ----------------- */

// Get current user id from session (if logged in)
$user_id = $_SESSION['user_id'] ?? null;

// If not logged in, redirect to login page
if (!$user_id) {
    header("Location: login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// For now, assume all users are premium.
// TODO: Replace this with real DB check (users.premium column).
$is_premium = true;

/* ----------------- LOAD USER FROM DB ----------------- */

try {
    // Select user info from database
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email, phone, address, job_category, current_position, profile_picture
        FROM users WHERE user_id=? LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If DB error, set user as null
    $user = null;
}

// Handle user not found
if (!$user) {
    http_response_code(404);
    $fatal_error = "User not found.";
}

// Handle non-premium access
if (!$is_premium && empty($fatal_error)) {
    http_response_code(403);
    $fatal_error = "Premium feature only. Please upgrade to access Premium Resume.";
}

/* ----------------- PREP DATA ----------------- */

// Extract user info or set default empty values
$name   = $user['full_name'] ?? '';
$email  = $user['email'] ?? '';
$phone  = $user['phone'] ?? '';
$addr   = $user['address'] ?? '';
$cat    = $user['job_category'] ?? '';
$pos    = $user['current_position'] ?? '';
$photo  = $user['profile_picture'] ?? '';

// Generate initials fallback (used if no profile picture)
$ini    = initials($name);

// Convert local profile photo to data URI (safe for canvas export)
$photo_src = '';
if ($photo) {
    $fs_path = __DIR__ . '/' . $PROFILE_DIR . $photo;
    $photo_src = data_uri_for_file($fs_path);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Premium Resume | JobHive</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Capture libs -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dom-to-image-more@3.3.0/dist/dom-to-image-more.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

    <style>
        /* ===================== Theme & Spacing ===================== */
        :root {
            --accent: #0ea5e9;
            --ink: #0f172a;
            --muted: #6b7280;
            --sp-1: 6px;
            --sp-2: 10px;
            --sp-3: 14px;
            --sp-4: 18px;
            --sp-5: 24px;
            --sp-6: 32px;
            --sp-7: 40px;
            --sp-8: 56px;
            --radius-lg: 16px;
            --shadow: 0 10px 30px rgba(0, 0, 0, .08);
            --line: 1.6;
        }

        body {
            background: #f8fafc;
            color: var(--ink);
            line-height: var(--line);
        }

        /* Color swatches */
        .template-picker .swatch {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, .1);
            cursor: pointer;
        }

        /* Compact template selector pills */
        .template-pills .btn-templ {
            border-radius: 999px;
            padding: .35rem .75rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .template-pills .btn-templ.active {
            color: #fff;
            background: var(--accent);
            border-color: var(--accent);
        }

        /* A4 preview surface */
        .resume-stage {
            background: #fff;
            width: 794px;
            min-height: 1123px;
            margin: 0 auto;
            box-shadow: var(--shadow);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .resume {
            color: var(--ink);
        }

        .divider {
            height: 2px;
            background: color-mix(in srgb, var(--accent) 18%, white);
            margin: var(--sp-5) 0 var(--sp-4);
        }

        .h-name {
            font-weight: 900;
            letter-spacing: .2px;
            margin-bottom: var(--sp-2);
        }

        .muted {
            color: var(--muted);
        }

        .stack>*+* {
            margin-top: var(--sp-3);
        }

        /* Template 1: Classic Sidebar */
        .t1 {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 1123px;
        }

        .t1 .side {
            background: color-mix(in srgb, var(--accent) 9%, white);
            padding: var(--sp-6) var(--sp-5);
            border-right: 3px solid var(--accent);
        }

        .t1 .main {
            padding: var(--sp-6) var(--sp-7);
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid rgba(0, 0, 0, .06);
            background: #fff;
        }

        .avatar-fallback {
            width: 120px;
            height: 120px;
            border-radius: 14px;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 34px;
        }

        .section-title {
            color: var(--accent);
            font-weight: 800;
            letter-spacing: .5px;
            margin-top: var(--sp-6);
            margin-bottom: var(--sp-2);
        }

        /* Template 2: Clean Header Bar */
        .t2 .head {
            background: var(--accent);
            color: #fff;
            padding: var(--sp-7) var(--sp-7) var(--sp-6);
        }

        .t2 .body {
            padding: var(--sp-6) var(--sp-7);
        }

        .t2 .badge-role {
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .35);
            padding: .35rem .7rem;
            border-radius: 999px;
        }

        .t2 .row.gx-6 {
            --bs-gutter-x: 3rem;
        }

        /* Template 3: Dark Sidebar */
        .t3 {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 1123px;
        }

        .t3 .left {
            background: #0b1220;
            color: #e5e7eb;
            padding: var(--sp-7) var(--sp-5);
        }

        .t3 .right {
            padding: var(--sp-7) var(--sp-7);
        }

        .t3 .circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--accent);
            object-fit: cover;
            background: #fff;
        }

        .t3 .chip {
            display: inline-block;
            padding: .35rem .7rem;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            margin-right: .35rem;
            margin-top: .35rem;
            background: rgba(255, 255, 255, .05);
        }

        @media (max-width:900px) {
            .resume-stage {
                width: 100%;
                border-radius: 0;
            }

            .t1,
            .t3 {
                grid-template-columns: 1fr;
            }

            .t1 .side {
                border-right: 0;
                border-bottom: 3px solid var(--accent);
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="home.php">JobHive</a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="user_home.php" class="btn btn-outline-secondary btn-sm">Back</a>
                <button id="btnPNG" class="btn btn-outline-secondary btn-sm">Download PNG</button>
                <button id="btnPDF" class="btn btn-warning btn-sm">Download PDF</button>
            </div>
        </div>
    </nav>

    <header class="py-4 bg-white border-bottom">
        <div class="container">
            <h1 class="h4 fw-bold mb-2">Select your template</h1>
            <div class="text-muted">Premium resume uses your saved profile—no typing needed.</div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <?php if (!empty($fatal_error)): ?>
                <div class="alert alert-warning"><?= e($fatal_error) ?></div>
            <?php else: ?>

                <!-- Color chooser -->
                <div class="row g-3 align-items-center mb-3 template-picker">
                    <div class="col-12 col-lg">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="me-2">Theme color</span>
                            <?php
                            $swatches = ['#111827', '#64748b', '#6b7280', '#1d4ed8', '#b91c1c', '#065f46', '#0ea5e9', '#ef4444', '#f59e0b', '#14b8a6', '#eab308'];
                            foreach ($swatches as $hex): ?>
                                <div class="swatch" data-color="<?= e($hex) ?>" style="background: <?= e($hex) ?>;"></div>
                            <?php endforeach; ?>
                            <span class="mx-2">or</span>
                            <input id="hexColor" type="text" class="form-control form-control-sm" value="#0EA5E9" style="max-width:120px;" />
                        </div>
                    </div>
                </div>

                <!-- Compact template buttons -->
                <div id="templatePills" class="template-pills d-flex flex-wrap gap-2 mb-4" role="tablist" aria-label="Choose resume template">
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-templ active" data-template="t1" aria-selected="true">Template 1</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-templ" data-template="t2" aria-selected="false">Template 2</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-templ" data-template="t3" aria-selected="false">Template 3</button>
                </div>

                <!-- Live A4 Preview -->
                <div id="resumeStage" class="resume-stage" style="--accent:#0ea5e9;">
                    <div id="resumeHost"></div>
                </div>

                <!-- ================= Templates ================= -->

                <!-- Template 1 -->
                <template id="tpl-t1">
                    <div class="resume t1">
                        <aside class="side">
                            <div class="stack">
                                <?php if ($photo_src): ?>
                                    <img src="<?= e($photo_src) ?>" alt="Profile" class="avatar">
                                <?php else: ?>
                                    <div class="avatar-fallback"><?= e($ini) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold"><?= e($email) ?></div>
                                    <div class="muted"><?= e($phone) ?></div>
                                    <div class="muted"><?= e($addr) ?></div>
                                </div>
                                <div>
                                    <div class="section-title">Key Info</div>
                                    <div><small>Category</small><br><strong><?= e($cat) ?></strong></div>
                                    <div class="mt-2"><small>Position</small><br><strong><?= e($pos) ?></strong></div>
                                </div>
                            </div>
                        </aside>

                        <section class="main">
                            <div>
                                <div class="display-6 h-name mb-1"><?= e($name) ?></div>
                                <?php if ($pos): ?><div class="fs-5 muted"><?= e($pos) ?></div><?php endif; ?>
                            </div>
                            <div class="divider"></div>
                            <div class="stack">
                                <div>
                                    <div class="section-title">Profile</div>
                                    <p class="mb-0 muted">This premium resume loads your saved details automatically. Add more fields to your profile to enrich this section.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </template>

                <!-- Template 2 -->
                <template id="tpl-t2">
                    <div class="resume t2">
                        <div class="head d-flex align-items-center gap-4">
                            <?php if ($photo_src): ?>
                                <img src="<?= e($photo_src) ?>" alt="Profile" class="avatar" style="border:3px solid rgba(255,255,255,.55);">
                            <?php else: ?>
                                <div class="avatar-fallback"><?= e($ini) ?></div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="display-6 fw-bold mb-1"><?= e($name) ?></div>
                                <span class="badge-role"><?= e($pos ?: $cat) ?></span>
                            </div>
                        </div>
                        <div class="body">
                            <div class="row gx-6 gy-4">
                                <div class="col-md-6">
                                    <div class="fw-semibold">Email</div>
                                    <div class="muted"><?= e($email) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fw-semibold">Phone</div>
                                    <div class="muted"><?= e($phone) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fw-semibold">Address</div>
                                    <div class="muted"><?= e($addr) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="fw-semibold">Category</div>
                                    <div class="muted"><?= e($cat) ?></div>
                                </div>
                            </div>
                            <div class="divider"></div>
                            <div>
                                <div class="fw-semibold mb-1">Current Position</div>
                                <div><?= e($pos) ?></div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Template 3 -->
                <template id="tpl-t3">
                    <div class="resume t3">
                        <div class="left">
                            <?php if ($photo_src): ?>
                                <img src="<?= e($photo_src) ?>" alt="Profile" class="circle">
                            <?php else: ?>
                                <div class="avatar-fallback" style="border-radius:50%;width:120px;height:120px;"><?= e($ini) ?></div>
                            <?php endif; ?>
                            <div class="mt-4">
                                <div><?= e($email) ?></div>
                                <div><?= e($phone) ?></div>
                                <div><?= e($addr) ?></div>
                            </div>
                            <div class="mt-4">
                                <?php if ($cat): ?><span class="chip"><?= e($cat) ?></span><?php endif; ?>
                                <?php if ($pos): ?><span class="chip"><?= e($pos) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="right">
                            <div class="display-6 fw-bold mb-1" style="color:var(--accent)"><?= e($name) ?></div>
                            <div class="fs-5 muted mb-4"><?= e($pos ?: $cat) ?></div>
                            <div class="fw-semibold">About</div>
                            <p class="muted">Complete more fields in your profile to improve this section.</p>
                            <div class="fw-semibold mt-4">Contact</div>
                            <ul class="mb-0">
                                <li><?= e($email) ?></li>
                                <li><?= e($phone) ?></li>
                                <li><?= e($addr) ?></li>
                            </ul>
                        </div>
                    </div>
                </template>

            <?php endif; ?>
        </div>
    </main>

    <footer class="mt-5 py-4 bg-dark text-white">
        <div class="container d-flex flex-column align-items-center">
            <small>&copy; 2025 JobHive. All rights reserved.</small>
        </div>
    </footer>

    <script>
        (function() {
            const stage = document.getElementById('resumeStage');
            const host = document.getElementById('resumeHost');
            const hexInput = document.getElementById('hexColor');
            const swatches = document.querySelectorAll('.swatch');
            const templBtns = document.querySelectorAll('.btn-templ');
            const btnPNG = document.getElementById('btnPNG');
            const btnPDF = document.getElementById('btnPDF');

            const tpl1 = document.getElementById('tpl-t1');
            const tpl2 = document.getElementById('tpl-t2');
            const tpl3 = document.getElementById('tpl-t3');

            function mountTemplate(tplEl) {
                host.innerHTML = '';
                host.appendChild(tplEl.content.cloneNode(true));
            }

            function setAccent(hex) {
                const v = hex.trim();
                if (!/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(v)) return;
                stage.style.setProperty('--accent', v);
                hexInput.value = v.toUpperCase();
            }

            function setTemplate(key) {
                templBtns.forEach(b => {
                    const isActive = b.dataset.template === key;
                    b.classList.toggle('active', isActive);
                    b.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                if (key === 't1') mountTemplate(tpl1);
                if (key === 't2') mountTemplate(tpl2);
                if (key === 't3') mountTemplate(tpl3);
            }

            // Init defaults
            setAccent('#0EA5E9');
            setTemplate('t1');

            // Events
            swatches.forEach(s => s.addEventListener('click', () => setAccent(s.dataset.color)));
            hexInput.addEventListener('change', () => setAccent(hexInput.value));
            templBtns.forEach(b => b.addEventListener('click', () => setTemplate(b.dataset.template)));

            // Helpers
            function isIOS() {
                return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            }
            async function ensureImages(node) {
                const imgs = Array.from(node.querySelectorAll('img'));
                await Promise.all(imgs.map(img => img.complete ? Promise.resolve() : new Promise(r => (img.onload = img.onerror = r))));
            }
            async function renderWithHtml2Canvas(node) {
                if (typeof html2canvas !== 'function') throw new Error('html2canvas not loaded');
                await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
                return html2canvas(node, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false,
                    imageTimeout: 15000
                });
            }
            async function renderBlobWithDomToImage(node) {
                if (!window.domtoimage) throw new Error('dom-to-image-more not loaded');
                return window.domtoimage.toBlob(node, {
                    cacheBust: true,
                    bgcolor: '#ffffff',
                    width: node.scrollWidth,
                    height: node.scrollHeight
                });
            }
            async function getCanvasOrBlob(node) {
                await ensureImages(node);
                try {
                    const canvas = await renderWithHtml2Canvas(node);
                    return {
                        kind: 'canvas',
                        value: canvas
                    };
                } catch (err) {
                    console.warn('html2canvas failed, fallback to dom-to-image-more:', err);
                    const blob = await renderBlobWithDomToImage(node);
                    return {
                        kind: 'blob',
                        value: blob
                    };
                }
            }

            // Download PNG
            btnPNG?.addEventListener('click', async () => {
                try {
                    const node = document.getElementById('resumeStage');
                    const out = await getCanvasOrBlob(node);
                    if (out.kind === 'canvas') {
                        const url = out.value.toDataURL('image/png');
                        if (isIOS()) window.open(url, '_blank');
                        else {
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'resume.png';
                            a.click();
                        }
                    } else {
                        const url = URL.createObjectURL(out.value);
                        if (isIOS()) window.open(url, '_blank');
                        else {
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'resume.png';
                            a.click();
                        }
                        URL.revokeObjectURL(url);
                    }
                } catch (err) {
                    alert('Could not generate PNG.\n' + (err?.message || err));
                    console.error(err);
                }
            });

            // Download PDF
            btnPDF?.addEventListener('click', async () => {
                try {
                    const node = document.getElementById('resumeStage');
                    const out = await getCanvasOrBlob(node);

                    const {
                        jsPDF
                    } = window.jspdf || {};
                    const PDFCtor = jsPDF || (window.jspdf && window.jspdf.jsPDF);
                    if (!PDFCtor) throw new Error('jsPDF not loaded');
                    const pdf = new PDFCtor('p', 'mm', 'a4');
                    const pageW = pdf.internal.pageSize.getWidth();
                    const pageH = pdf.internal.pageSize.getHeight();

                    if (out.kind === 'canvas') {
                        const canvas = out.value;
                        const imgData = canvas.toDataURL('image/png');
                        const imgW = pageW,
                            imgH = (canvas.height * imgW) / canvas.width;
                        if (imgH <= pageH) {
                            pdf.addImage(imgData, 'PNG', 0, 0, imgW, imgH, undefined, 'FAST');
                        } else {
                            const pageWmm = 210,
                                pageHmm = 297;
                            const pxPerMm = canvas.width / pageWmm;
                            const pxPageH = Math.floor(pageHmm * pxPerMm);
                            const sliceCanvas = document.createElement('canvas');
                            const ctx = sliceCanvas.getContext('2d');
                            sliceCanvas.width = canvas.width;
                            sliceCanvas.height = pxPageH;
                            let sY = 0,
                                page = 0;
                            while (sY < canvas.height) {
                                ctx.clearRect(0, 0, sliceCanvas.width, sliceCanvas.height);
                                ctx.drawImage(canvas, 0, sY, canvas.width, pxPageH, 0, 0, canvas.width, pxPageH);
                                const chunk = sliceCanvas.toDataURL('image/png');
                                if (page) pdf.addPage();
                                pdf.addImage(chunk, 'PNG', 0, 0, pageW, pageH, undefined, 'FAST');
                                sY += pxPageH;
                                page++;
                            }
                        }
                    } else {
                        const blob = out.value;
                        const blobUrl = URL.createObjectURL(blob);
                        const img = new Image();
                        img.src = blobUrl;
                        await new Promise(r => (img.onload = img.onerror = r));
                        const c2 = document.createElement('canvas');
                        c2.width = img.naturalWidth;
                        c2.height = img.naturalHeight;
                        c2.getContext('2d').drawImage(img, 0, 0);
                        const imgData = c2.toDataURL('image/png');
                        const ratio = Math.min(pageW / c2.width, pageH / c2.height);
                        pdf.addImage(imgData, 'PNG', 0, 0, c2.width * ratio, c2.height * ratio, undefined, 'FAST');
                        URL.revokeObjectURL(blobUrl);
                    }

                    if (isIOS()) {
                        const blobUrl = pdf.output('bloburl');
                        window.open(blobUrl, '_blank');
                    } else {
                        pdf.save('resume.pdf');
                    }
                } catch (err) {
                    alert('Could not generate PDF.\n' + (err?.message || err));
                    console.error(err);
                }
            });
        })();
    </script>
</body>

</html>