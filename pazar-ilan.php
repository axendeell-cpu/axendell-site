<?php
/**
 * /pazar-ilan.php?id=X
 * Tek ilan detayi + galeri + "Satin Al" -> sifreli kod uretici (encoder).
 *
 * Kod formati: AXD-XXXX-XXXX-XXXX (Base32, AES-256-GCM)
 *  - Plain: listing_id|buyer_user_id|price|timestamp|nonce
 *  - Decoder: /admin/satis-cozucu.php (mirror)
 */
require_once __DIR__ . '/db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getSet($conn, $key, $def = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return $def;
    $stmt->bind_param('s', $key); $stmt->execute(); $stmt->bind_result($v);
    $val = $def; if ($stmt->fetch()) $val = $v; $stmt->close(); return $val;
}

// Encoder - admin/satis-cozucu.php decode_sale_code() ile uyumlu
function encode_sale_code($listing_id, $buyer_user_id, $price, $secret) {
    $nonce = bin2hex(random_bytes(3)); // 6 karakter
    $plain = $listing_id . '|' . $buyer_user_id . '|' . $price . '|' . time() . '|' . $nonce;

    $key = hash('sha256', $secret, true);
    $iv  = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return null;

    $bytes = $iv . $cipher . $tag;

    // Base32 encode
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0; $i < strlen($bytes); $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    // 5'lik gruplara tamamla
    $padding = (5 - (strlen($bits) % 5)) % 5;
    if ($padding > 0) $bits .= str_repeat('0', $padding);
    $out = '';
    for ($i = 0; $i < strlen($bits); $i += 5) {
        $out .= $alphabet[bindec(substr($bits, $i, 5))];
    }

    // AXD prefix + dash formatla (4'lu gruplar)
    $full = 'AXD' . $out;
    return implode('-', str_split($full, 4));
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) { header('Location: /pazar.php'); exit; }

// Ilanı çek
$stmt = $conn->prepare("SELECT l.id, l.user_id, l.title, l.price, l.image, l.rank_name, l.skins, l.winrate,
                              l.description, l.view_count, l.status, l.approved_at, l.created_at,
                              u.name, u.avatar, u.rating_avg, u.total_sales, u.created_at as u_created
                       FROM user_listings l
                       JOIN users u ON u.id = l.user_id
                       WHERE l.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($lid,$uid,$title,$price,$image,$rname,$skins,$wr,$desc,$views,$status,$approved,$created,$sname,$savatar,$srating,$stsales,$su_created);
$found = $stmt->fetch();
$stmt->close();

if (!$found || $status !== 'approved') {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>İlan Bulunamadı</title></head>';
    echo '<body style="background:#0a0a0a;color:#fff;font-family:Inter,sans-serif;text-align:center;padding:80px">';
    echo '<h1 style="color:#FFD700">İlan Bulunamadı</h1><p style="color:#999">Bu ilan kaldırılmış, satılmış veya hiç var olmamış olabilir.</p>';
    echo '<a href="/pazar.php" style="display:inline-block;margin-top:20px;padding:12px 28px;background:#FFD700;color:#000;border-radius:8px;text-decoration:none;font-weight:600">← Pazara Dön</a>';
    echo '</body></html>';
    exit;
}

// View count (sadece GET'te)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conn->query("UPDATE user_listings SET view_count = view_count + 1 WHERE id = " . (int)$lid);
}

// Galeri
$gallery = [];
$stmt = $conn->prepare("SELECT image_path FROM user_listing_images WHERE listing_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param('i', $lid);
$stmt->execute();
$stmt->bind_result($ipath);
while ($stmt->fetch()) $gallery[] = $ipath;
$stmt->close();

// Kapak fotoğrafını galerinin başına ekle (eğer galeride yoksa)
if ($image && !in_array($image, $gallery)) {
    array_unshift($gallery, $image);
}
$gallery = array_values(array_unique(array_filter($gallery)));

// Admin WhatsApp once cek (asagidaki kod uretiminde de gerek var)
$admin_wa = getSet($conn, 'admin_whatsapp_number', '');

// Kod üretildi mi?
$generated_code = null;
$buyer_user_id = isset($_SESSION['uye_user_id']) ? (int)$_SESSION['uye_user_id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_code') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $buyer_user_id = isset($_SESSION['uye_user_id']) ? (int)$_SESSION['uye_user_id'] : 0;
    $secret = getSet($conn, 'sale_code_secret');
    if (!$secret) {
        $error = 'Sistem yapılandırması eksik. Lütfen admin ile iletişime geçin.';
    } else {
        $generated_code = encode_sale_code($lid, $buyer_user_id, (int)$price, $secret);
        if (!$generated_code) {
            $error = 'Kod üretilirken bir hata oluştu.';
        } elseif ($admin_wa) {
            // OTOMATIK YONLENDIRME: Hazir mesajla WhatsApp'a git
            $wa_msg = "Merhaba! Üye pazarından bir hesap satın almak istiyorum.\n\n"
                    . "📦 İlan: " . $title . "\n"
                    . "💰 Fiyat: " . number_format((int)$price,0,',','.') . " TL\n"
                    . "🆔 İlan No: #" . (int)$lid . "\n\n"
                    . "🔐 Satış Kodum:\n" . $generated_code . "\n\n"
                    . "Yukarıdaki kodu çözüp ödeme bilgilerini iletebilir misiniz? Teşekkürler.";
            $wa_clean = preg_replace('/[^0-9]/', '', $admin_wa);
            $wa_url = 'https://wa.me/' . $wa_clean . '?text=' . urlencode($wa_msg);
            header('Location: ' . $wa_url);
            exit;
        }
    }
}
$logo = '/uploads/logo.png';
$site_name = 'AXENDELL';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> - Üye İlanı | Axendell</title>
<meta name="description" content="<?= h(mb_substr($desc, 0, 160)) ?>">
<link rel="icon" href="<?= h($logo) ?>">
<meta property="og:title" content="<?= h($title) ?> - <?= number_format($price,0,',','.') ?> TL">
<meta property="og:image" content="<?= h($image ?: $logo) ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/pdp.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0a0a0a;color:#fff;min-height:100vh;
background-image:radial-gradient(circle at 20% 0%, rgba(123,42,191,0.15) 0%, transparent 50%);}
a{color:inherit;text-decoration:none}
.container{max-width:1200px;margin:0 auto;padding:24px}

.hdr{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;background:rgba(10,10,10,0.85);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,215,0,0.12);position:sticky;top:0;z-index:100}
.hdr .brand{display:flex;align-items:center;gap:12px;font-family:'Playfair Display',serif;font-size:22px;color:#FFD700}
.hdr .brand img{width:36px;height:36px;border-radius:8px;background:#000;padding:4px;border:1px solid rgba(255,215,0,0.2)}
.hdr nav{display:flex;gap:24px;align-items:center}
.hdr nav a{font-size:14px;color:#bbb;font-weight:500}
.hdr nav a:hover, .hdr nav a.active{color:#FFD700}
@media(max-width:760px){.hdr nav{display:none}}

.crumbs{font-size:13px;color:#777;margin-bottom:18px}
.crumbs a{color:#999}.crumbs a:hover{color:#FFD700}

.layout{display:grid;grid-template-columns:1.3fr 1fr;gap:28px;align-items:start}
@media(max-width:900px){.layout{grid-template-columns:1fr}}

/* Galeri */
.gallery{background:linear-gradient(145deg,#141414,#0d0d0d);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:14px}
.gallery .main-img{width:100%;aspect-ratio:4/3;background:#000;border-radius:12px;overflow:hidden;margin-bottom:10px;display:flex;align-items:center;justify-content:center;position:relative}
.gallery .main-img img{width:100%;height:100%;object-fit:contain;cursor:zoom-in}
.gallery .empty{color:#333;font-size:64px}
.gallery .thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px}
.gallery .thumbs img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;cursor:pointer;border:2px solid transparent;transition:all 0.15s}
.gallery .thumbs img:hover{border-color:rgba(255,215,0,0.6)}
.gallery .thumbs img.active{border-color:#FFD700}

/* Detay */
.detail-card{background:linear-gradient(145deg,#141414,#0d0d0d);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:24px}
.detail-card .verified{display:inline-block;padding:5px 12px;background:rgba(37,211,102,0.15);color:#25D366;border:1px solid rgba(37,211,102,0.3);border-radius:99px;font-size:11px;font-weight:700;letter-spacing:0.5px;margin-bottom:14px}
.detail-card h1{font-family:'Playfair Display',serif;font-size:28px;color:#fff;margin-bottom:14px;line-height:1.2}
.detail-card .price{font-size:36px;font-weight:700;color:#FFD700;margin-bottom:18px}
.detail-card .price small{font-size:14px;color:#999;font-weight:400;display:block;margin-top:4px}
.specs-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
.spec-item{padding:12px;background:#0a0a0a;border:1px solid #222;border-radius:10px;text-align:center}
.spec-item .label{font-size:10px;color:#666;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:4px}
.spec-item .value{font-size:15px;font-weight:600;color:#fff}
.btn-buy{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;background:linear-gradient(135deg,#FFD700,#FFA500);color:#000;border:none;border-radius:12px;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit;text-decoration:none;transition:all 0.15s}
.btn-buy:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(255,215,0,0.3)}

.seller-card{background:linear-gradient(145deg,#141414,#0d0d0d);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:18px;margin-top:18px;display:flex;gap:14px;align-items:center}
.seller-card .avatar{width:56px;height:56px;border-radius:50%;background:#222;border:2px solid rgba(255,215,0,0.3);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:28px;color:#666}
.seller-card .avatar img{width:100%;height:100%;object-fit:cover}
.seller-card .info{flex:1;min-width:0}
.seller-card .info .name{font-size:16px;font-weight:600;color:#fff;margin-bottom:4px}
.seller-card .info .meta{font-size:12px;color:#888;display:flex;gap:14px;flex-wrap:wrap}
.seller-card .info .meta span{display:flex;align-items:center;gap:4px}

/* Açıklama */
.desc-card{background:linear-gradient(145deg,#141414,#0d0d0d);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:24px;margin-top:24px}
.desc-card h3{color:#FFD700;font-family:'Playfair Display',serif;font-size:20px;margin-bottom:14px}
.desc-card .content{color:#ccc;font-size:14px;line-height:1.7;white-space:pre-wrap}

/* Modal */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px}
.modal-bg.show{display:flex}
.modal{background:linear-gradient(145deg,#141414,#0d0d0d);border:1px solid rgba(255,215,0,0.3);border-radius:18px;padding:30px;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;position:relative}
.modal h2{color:#FFD700;font-family:'Playfair Display',serif;font-size:24px;margin-bottom:14px}
.modal .close{position:absolute;top:14px;right:18px;font-size:28px;color:#666;cursor:pointer;background:none;border:none}
.modal .close:hover{color:#fff}
.modal p{color:#ccc;font-size:14px;line-height:1.6;margin-bottom:14px}
.modal .code-box{padding:18px;background:#000;border:2px dashed #FFD700;border-radius:12px;text-align:center;margin:18px 0}
.modal .code-box .code{font-family:'Courier New',monospace;font-size:22px;color:#FFD700;font-weight:700}
.modal .step{padding:14px;background:rgba(255,215,0,0.06);border-left:3px solid #FFD700;border-radius:8px;margin-bottom:10px}
.modal .step b{color:#FFD700}
.modal .wa-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:14px;background:#25D366;color:#fff;border:none;border-radius:12px;font-weight:600;cursor:pointer;font-size:15px;font-family:inherit;text-decoration:none;margin-top:14px}
.modal .wa-btn:hover{background:#1eb755}

.alert-warn{padding:14px;background:rgba(255,215,0,0.1);border:1px solid #FFD700;border-radius:10px;color:#FFD700;font-size:13px;margin-top:14px}
.alert-err{padding:14px;background:rgba(239,68,68,0.12);border:1px solid #ef4444;border-radius:10px;color:#fca5a5;font-size:13px;margin-bottom:14px}

footer{margin-top:60px;padding:30px 24px;text-align:center;color:#555;font-size:12px;border-top:1px solid rgba(255,255,255,0.05)}
footer a{color:#888;margin:0 10px}
</style>
</head>
<body>

<header class="hdr">
    <a href="/" class="brand">
        <img src="<?= h($logo) ?>" alt="">
        <?= h($site_name) ?>
    </a>
    <nav>
        <a href="/">Ana Sayfa</a>
        <a href="/pazar.php" class="active">Üye Pazarı</a>
        <a href="/uye/panel.php">Üye Paneli</a>
    </nav>
</header>

<div class="container">

    <div class="crumbs">
        <a href="/">Ana Sayfa</a> › <a href="/pazar.php">Üye Pazarı</a> › <?= h($title) ?>
    </div>

    <div class="layout">

        <!-- Sol: Galeri + Açıklama -->
        <div>
            <div class="gallery">
                <div class="main-img" id="mainImg">
                    <?php $first = $gallery[0] ?? ''; ?>
                    <?php if ($first): ?>
                        <button type="button" class="ax-pdp__zoom-btn" id="axPdpZoom" aria-label="Fotoğrafı büyüt">
                            <img src="<?= h($first) ?>" alt="<?= h($title) ?>" id="mainPhoto" class="ax-pdp__hero-img">
                            <span class="ax-pdp__zoom-icon" aria-hidden="true">⤢</span>
                        </button>
                    <?php else: ?>
                        <div class="empty">📷</div>
                    <?php endif; ?>
                </div>
                <?php if (count($gallery) > 1): ?>
                    <div class="thumbs">
                        <?php foreach ($gallery as $idx => $g): ?>
                            <img src="<?= h($g) ?>" class="ax-pdp__thumb <?= $idx===0?'active is-active':'' ?>" data-src="<?= h($g) ?>" onclick="document.getElementById('mainPhoto').src='<?= h($g) ?>';document.querySelectorAll('.thumbs img').forEach(t=>t.classList.remove('active','is-active'));this.classList.add('active','is-active')">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (trim($desc)): ?>
                <div class="desc-card">
                    <h3>📝 İlan Açıklaması</h3>
                    <div class="content"><?= h($desc) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sağ: Detay + Satıcı + Satın Al -->
        <div>
            <div class="detail-card">
                <div class="verified">✓ ADMİN ONAYLI</div>
                <h1><?= h($title) ?></h1>
                <div class="price">
                    <?= number_format($price,0,',','.') ?> ₺
                    <small>👁 <?= (int)$views ?> görüntülenme · <?= h($rname) ?></small>
                </div>

                <div class="specs-grid">
                    <div class="spec-item">
                        <div class="label">Rank</div>
                        <div class="value">🏆 <?= h($rname) ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="label">Skin Sayısı</div>
                        <div class="value">✨ <?= (int)$skins ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="label">Winrate</div>
                        <div class="value">📊 <?= number_format((float)$wr,1,',','.') ?>%</div>
                    </div>
                    <div class="spec-item">
                        <div class="label">Yayın Tarihi</div>
                        <div class="value" style="font-size:12px"><?= date('d.m.Y', strtotime($approved ?: $created)) ?></div>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert-err"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($generated_code): ?>
                    <button class="btn-buy" onclick="document.getElementById('codeModal').classList.add('show')">
                        ✓ Kodun Tekrar Göster
                    </button>
                <?php else: ?>
                    <form method="post" id="buyForm">
                        <input type="hidden" name="action" value="generate_code">
                        <button type="submit" class="btn-buy" style="background:linear-gradient(135deg,#25D366,#1eb755);color:#fff">
                            💬 Satın Al — WhatsApp ile Devam Et
                        </button>
                    </form>
                <?php endif; ?>

                <div class="alert-warn">
                    <b>🔒 Güvenli Satış:</b> Tüm satışlar admin aracılığıyla gerçekleştirilir. Kod üretildikten sonra WhatsApp ile bize ulaşın.
                </div>
            </div>

            <div class="seller-card">
                <div class="avatar">
                    <?php if ($savatar): ?>
                        <img src="<?= h($savatar) ?>" alt="">
                    <?php else: ?>
                        👤
                    <?php endif; ?>
                </div>
                <div class="info">
                    <div class="name"><?= h($sname) ?></div>
                    <div class="meta">
                        <?php if ((float)$srating > 0): ?>
                            <span>⭐ <?= number_format((float)$srating,1,',','.') ?></span>
                        <?php endif; ?>
                        <span>💰 <?= (int)$stsales ?> satış</span>
                        <span>📅 <?= date('Y', strtotime($su_created)) ?> üyesi</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Satın Al Kod Modal -->
<?php if ($generated_code): ?>
<div class="modal-bg show" id="codeModal">
    <div class="modal">
        <button class="close" onclick="document.getElementById('codeModal').classList.remove('show')">×</button>
        <h2>🛒 Satış Kodunuz</h2>
        <p>Bu hesabı satın almak için aşağıdaki adımları takip edin:</p>

        <div class="code-box">
            <div class="code" id="saleCode"><?= h($generated_code) ?></div>
            <button class="copy-btn" onclick="copyCode()">📋 Kodu Kopyala</button>
        </div>

        <div class="step"><b>1.</b> Yukarıdaki kodu kopyalayın.</div>
        <div class="step"><b>2.</b> WhatsApp üzerinden adminimize yazın ve kodu gönderin.</div>
        <div class="step"><b>3.</b> Admin kodu çözecek, ödeme bilgilerini ve hesap detaylarını size gönderecek.</div>
        <div class="step"><b>4.</b> Ödeme yapın → admin satıcıdan hesap bilgilerini alıp size aktarır.</div>

        <?php
        $wa_msg = "Merhaba, üye pazarından bir hesap satın almak istiyorum.\n\nİlan: " . $title . "\nFiyat: " . number_format($price,0,',','.') . " TL\nKod: " . $generated_code;
        $wa_link = $admin_wa
            ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $admin_wa) . '?text=' . urlencode($wa_msg)
            : '#';
        ?>
        <?php if ($admin_wa): ?>
            <a href="<?= h($wa_link) ?>" target="_blank" class="wa-btn">
                💬 WhatsApp ile Admine Yaz
            </a>
        <?php else: ?>
            <div class="alert-warn" style="margin-top:14px">
                Admin WhatsApp numarası ayarlanmamış. Lütfen kodu manuel olarak iletişim kanalından gönderin.
            </div>
        <?php endif; ?>

        <p style="font-size:11px;color:#666;margin-top:16px;text-align:center">
            ⏱ Kod 24 saat geçerlidir · Bu kodu kimseyle paylaşmayın
        </p>
    </div>
</div>
<script>
function copyCode() {
    const code = document.getElementById('saleCode').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target;
        const orig = btn.innerText;
        btn.innerText = '✓ Kopyalandı!';
        setTimeout(() => btn.innerText = orig, 1500);
    });
}
</script>
<?php endif; ?>

<!-- AX Lightbox (Faz 3.D) -->
<?php if (!empty($gallery)): ?>
<div class="ax-lightbox" id="axLightbox" role="dialog" aria-modal="true" aria-label="Fotoğraf görüntüleyici" hidden>
    <button type="button" class="ax-lightbox__close" id="axLbClose" aria-label="Kapat">×</button>
    <button type="button" class="ax-lightbox__nav ax-lightbox__nav--prev" id="axLbPrev" aria-label="Önceki">‹</button>
    <button type="button" class="ax-lightbox__nav ax-lightbox__nav--next" id="axLbNext" aria-label="Sonraki">›</button>
    <div class="ax-lightbox__stage" id="axLbStage">
        <img class="ax-lightbox__img" id="axLbImg" src="" alt="">
    </div>
    <div class="ax-lightbox__counter" id="axLbCounter">1 / <?= count($gallery) ?></div>
</div>
<script>window.AX_PHOTOS = <?= json_encode(array_values($gallery), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>

<script src="assets/js/app.js" defer></script>

<footer>
    <a href="/">Ana Sayfa</a> ·
    <a href="/pazar.php">Üye Pazarı</a><br>
    <div style="margin-top:10px">© <?= date('Y') ?> Axendell</div>
</footer>
</body>
</html>
