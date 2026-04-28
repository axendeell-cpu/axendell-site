<?php
/* =============================================================
   AXENDELL — ilan.php  (Premium Product Detail Page · Faz 2.2)
   ============================================================= */
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once 'db.php';

function getSettingPdp($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return '';
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $value = '';
    $stmt->bind_result($value);
    $stmt->fetch();
    $stmt->close();
    return $value;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$whatsapp_number  = '905358594798';
$site_title       = getSettingPdp($conn, 'site_title')       ?: 'AXENDELL';
$site_description = getSettingPdp($conn, 'site_description') ?: 'AXENDELL Mobile Legends Hesap Mağazası';
$instagram        = getSettingPdp($conn, 'instagram');
$telegram         = getSettingPdp($conn, 'telegram');

$maintenance_mode    = getSettingPdp($conn, 'maintenance_mode');
$announcement_active = getSettingPdp($conn, 'announcement_active');
$announcement_text   = getSettingPdp($conn, 'announcement_text');
$announcement_color  = getSettingPdp($conn, 'announcement_color') ?: 'gold';

if ($maintenance_mode === '1') {
    http_response_code(503);
    echo '<!DOCTYPE html><title>Bakım</title><body style="background:#0a0a0a;color:#fff;text-align:center;padding:80px;font-family:sans-serif"><h1 style="color:#FFD700">Bakım Modu</h1></body>';
    exit;
}

/* --- Hesabı çek --- */
$acc = null;
$stmt = $conn->prepare("SELECT id, title, price, old_price, image, rank_name, skins, winrate, sold, category, featured FROM accounts WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($a_id, $a_title, $a_price, $a_old, $a_img, $a_rank, $a_skins, $a_wr, $a_sold, $a_cat, $a_feat);
    if ($stmt->fetch()) {
        $acc = [
            'id' => $a_id, 'title' => $a_title, 'price' => $a_price, 'old_price' => $a_old,
            'image' => $a_img, 'rank_name' => $a_rank, 'skins' => $a_skins, 'winrate' => $a_wr,
            'sold' => $a_sold, 'category' => $a_cat, 'featured' => $a_feat,
        ];
    }
    $stmt->close();
}

if (!$acc) { http_response_code(404); } else {
    // Görüntülenme sayısını artır
    $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $check_click = $conn->prepare("SELECT id FROM account_clicks WHERE account_id = ? AND ip_hash = ? AND clicked_at >= CURDATE() LIMIT 1");
    $check_click->bind_param('is', $id, $ip_hash);
    $check_click->execute();
    if (!$check_click->fetch()) {
        $insert_click = $conn->prepare("INSERT INTO account_clicks (account_id, ip_hash) VALUES (?, ?)");
        $insert_click->bind_param('is', $id, $ip_hash);
        $insert_click->execute();
        $insert_click->close();
    }
    $check_click->close();
}

/* --- Galeri --- */
$photos = [];
if ($acc) {
    $gstmt = $conn->prepare("SELECT image_path FROM account_images WHERE account_id = ? ORDER BY sort_order ASC");
    if ($gstmt) {
        $gstmt->bind_param('i', $id);
        $gstmt->execute();
        $gpath = '';
        $gstmt->bind_result($gpath);
        while ($gstmt->fetch()) { $photos[] = $gpath; }
        $gstmt->close();
    }
    // Kapak fotoğrafını galerinin başına ekle (eğer galeride yoksa)
    if (!empty($acc['image']) && !in_array($acc['image'], $photos)) { 
        array_unshift($photos, $acc['image']); 
    }
    $photos = array_values(array_unique(array_filter($photos)));
}

/* --- Benzer hesaplar --- */
$related = [];
if ($acc) {
    $rstmt = $conn->prepare("SELECT id, title, price, old_price, image, rank_name, skins, winrate, sold, category, featured FROM accounts WHERE id <> ? AND category = ? AND sold = 0 ORDER BY featured DESC, sort_order ASC, id DESC LIMIT 3");
    if ($rstmt) {
        $rcat = $acc['category'];
        $rstmt->bind_param('is', $id, $rcat);
        $rstmt->execute();
        $rstmt->store_result();
        $rstmt->bind_result($r_id, $r_title, $r_price, $r_old, $r_img, $r_rank, $r_skins, $r_wr, $r_sold, $r_cat, $r_feat);
        while ($rstmt->fetch()) {
            $related[] = [
                'id' => $r_id, 'title' => $r_title, 'price' => $r_price, 'old_price' => $r_old,
                'image' => $r_img, 'rank_name' => $r_rank, 'skins' => $r_skins, 'winrate' => $r_wr,
                'sold' => $r_sold, 'category' => $r_cat, 'featured' => $r_feat,
            ];
        }
        $rstmt->close();
    }
}

$discount = 0;
if ($acc && !empty($acc['old_price']) && (float)$acc['old_price'] > (float)$acc['price'] && (float)$acc['price'] > 0) {
    $discount = (int)round((1 - (float)$acc['price'] / (float)$acc['old_price']) * 100);
}

$pageTitle = $acc ? htmlspecialchars($acc['title']) . ' — AXENDELL' : 'Hesap bulunamadı — AXENDELL';
$pageDesc  = $acc
    ? 'AXENDELL #' . (int)$acc['id'] . ' · ' . (int)$acc['skins'] . ' Skin · ' . htmlspecialchars($acc['rank_name']) . ' · Doğrulanmış MLBB hesabı.'
    : 'Hesap bulunamadı.';
$waMsg = $acc
    ? "Merhaba, AXENDELL #{$acc['id']} ({$acc['title']}) hesabıyla ilgileniyorum."
    : 'Merhaba AXENDELL.';
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.axendell.com/ilan.php?id=<?= (int)$id ?>">
    <meta name="theme-color" content="#0B0B0F">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://axendell.com/ilan.php?id=<?= (int)$id ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <?php if ($acc): ?>
        <?php 
            $ogImage = !empty($acc['image']) ? $acc['image'] : (!empty($photos[0]) ? $photos[0] : '');
            if ($ogImage && strpos($ogImage, 'http') !== 0) {
                $ogImage = 'https://axendell.com/' . ltrim($ogImage, '/');
            }
        ?>
        <?php if ($ogImage): ?>
        <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <?php endif; ?>
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $pageTitle ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <?php if (isset($ogImage) && $ogImage): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
    <?php endif; ?>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='18' fill='%230B0B0F'/><text x='50' y='66' text-anchor='middle' font-family='Playfair Display,serif' font-weight='800' font-size='58' fill='%23D4AF37'>A</text></svg>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/legacy.css">
    <link rel="stylesheet" href="assets/css/sections.css">
    <link rel="stylesheet" href="assets/css/sections-v2.css">
    <link rel="stylesheet" href="assets/css/polish.css">
    <link rel="stylesheet" href="assets/css/pdp.css">

    <?php if ($acc): ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "Product",
          "@id": "https://www.axendell.com/ilan.php?id=<?= (int)$acc['id'] ?>#product",
          "name": <?= json_encode($acc['title'], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>,
          "sku": "AXE-<?= (int)$acc['id'] ?>",
          "description": <?= json_encode($pageDesc, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>,
          <?php if (!empty($photos)): ?>
          "image": <?= json_encode(array_values($photos), JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>,
          <?php endif; ?>
          "category": <?= json_encode($acc['category'] ?: 'MLBB Account', JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>,
          "brand": { "@type": "Brand", "name": "AXENDELL" },
          "offers": {
            "@type": "Offer",
            "url": "https://www.axendell.com/ilan.php?id=<?= (int)$acc['id'] ?>",
            "priceCurrency": "TRY",
            "price": "<?= number_format((float)$acc['price'], 2, '.', '') ?>",
            "priceValidUntil": "<?= date('Y-m-d', strtotime('+30 days')) ?>",
            "availability": "<?= ((int)$acc['sold'] === 1) ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock' ?>",
            "itemCondition": "https://schema.org/NewCondition",
            "seller": { "@type": "Organization", "name": "AXENDELL" },
            "hasMerchantReturnPolicy": {
              "@type": "MerchantReturnPolicy",
              "applicableCountry": "TR",
              "returnPolicyCategory": "https://schema.org/MerchantReturnNotPermitted"
            },
            "shippingDetails": {
              "@type": "OfferShippingDetails",
              "shippingRate": {
                "@type": "MonetaryAmount",
                "value": "0",
                "currency": "TRY"
              },
              "shippingDestination": {
                "@type": "DefinedRegion",
                "addressCountry": "TR"
              },
              "deliveryTime": {
                "@type": "ShippingDeliveryTime",
                "handlingTime": {
                  "@type": "QuantitativeValue",
                  "minValue": 0,
                  "maxValue": 0,
                  "unitCode": "DAY"
                },
                "transitTime": {
                  "@type": "QuantitativeValue",
                  "minValue": 0,
                  "maxValue": 0,
                  "unitCode": "DAY"
                }
              }
            }
          }
        },
        {
          "@type": "BreadcrumbList",
          "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Anasayfa", "item": "https://www.axendell.com/" },
            { "@type": "ListItem", "position": 2, "name": "Hesaplar", "item": "https://www.axendell.com/#accounts" },
            { "@type": "ListItem", "position": 3, "name": <?= json_encode($acc['title'], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?> }
          ]
        }
      ]
    }
    </script>
    <?php endif; ?>
</head>
<body class="ax-pdp-body">

<div class="ax-progress" aria-hidden="true"><span class="ax-progress__bar"></span></div>

<?php include 'includes/header.php'; ?>

<?php if (!$acc): ?>
    <section class="ax-section ax-pdp-empty">
        <div class="ax-container" style="text-align:center;padding:120px 0">
            <span class="ax-eyebrow">404</span>
            <h1 class="ax-section__title">Hesap bulunamadı</h1>
            <p class="ax-section__sub">Aradığın hesap satılmış ya da kaldırılmış olabilir.</p>
            <a href="index.php" class="ax-btn ax-btn--primary" style="margin-top:24px">Hesap Vitrinine Dön</a>
        </div>
    </section>
<?php else: ?>

<nav class="ax-breadcrumb" aria-label="Sayfa yolu">
    <div class="ax-container">
        <a href="index.php">Anasayfa</a>
        <span aria-hidden="true">›</span>
        <a href="index.php#accounts">Hesaplar</a>
        <span aria-hidden="true">›</span>
        <span class="ax-breadcrumb__current"><?= htmlspecialchars($acc['title']) ?></span>
    </div>
</nav>

<section class="ax-section ax-pdp">
    <div class="ax-container ax-pdp__grid">

        <!-- GALERİ -->
        <div class="ax-pdp__gallery ax-reveal">
            <div class="ax-pdp__stage">
                <?php if (!empty($photos)): ?>
                    <button type="button" class="ax-pdp__zoom-btn" id="axPdpZoom" aria-label="Fotoğrafı büyüt">
                        <img class="ax-pdp__hero-img" id="axPdpStage" src="<?= htmlspecialchars($photos[0]) ?>" alt="<?= htmlspecialchars($acc['title']) ?>">
                        <span class="ax-pdp__zoom-icon" aria-hidden="true">⤢</span>
                    </button>
                <?php else: ?>
                    <div class="ax-pdp__hero-img ax-pdp__hero-img--empty">Fotoğraf yok</div>
                <?php endif; ?>

                <div class="ax-pdp__stage-badges">
                    <?php if ((int)$acc['featured'] === 1): ?>
                        <span class="ax-badge ax-badge--feat">★ Öne Çıkan</span>
                    <?php endif; ?>
                    <?php if ($discount > 0): ?>
                        <span class="ax-badge ax-badge--deal">%<?= $discount ?> İndirim</span>
                    <?php endif; ?>
                    <?php if ((int)$acc['sold'] === 1): ?>
                        <span class="ax-badge ax-badge--sold">Satıldı</span>
                    <?php else: ?>
                        <span class="ax-badge ax-badge--active">● Stokta</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($photos) > 1): ?>
            <div class="ax-pdp__thumbs" role="tablist" aria-label="Galeri">
                <?php foreach ($photos as $i => $p): ?>
                    <button type="button"
                            class="ax-pdp__thumb<?= $i === 0 ? ' is-active' : '' ?>"
                            data-src="<?= htmlspecialchars($p) ?>"
                            aria-label="Fotoğraf <?= $i + 1 ?>"
                            onclick="document.getElementById('axPdpStage').src='<?= htmlspecialchars($p) ?>';document.querySelectorAll('.ax-pdp__thumb').forEach(t=>t.classList.remove('is-active'));this.classList.add('is-active')">
                        <img src="<?= htmlspecialchars($p) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- BİLGİ + CTA -->
        <aside class="ax-pdp__info ax-reveal" style="--i:1">
            <span class="ax-eyebrow">Premium MLBB Hesabı · #<?= (int)$acc['id'] ?></span>
            <h1 class="ax-pdp__title"><?= htmlspecialchars($acc['title']) ?></h1>

            <div class="ax-pdp__meta">
                <?php if (!empty($acc['rank_name'])): ?>
                    <span class="ax-pdp__chip"><?= htmlspecialchars($acc['rank_name']) ?></span>
                <?php endif; ?>
                <span class="ax-pdp__chip"><?= (int)$acc['skins'] ?> Skin</span>
                <span class="ax-pdp__chip">Win Rate %<?= number_format((float)$acc['winrate'], 1, ',', '') ?></span>
            </div>

            <div class="ax-pdp__price-card">
                <div class="ax-pdp__price-row">
                    <?php if (!empty($acc['old_price']) && (float)$acc['old_price'] > (float)$acc['price']): ?>
                        <span class="ax-pdp__old-price"><?= number_format((float)$acc['old_price'], 0, ',', '.') ?> ₺</span>
                    <?php endif; ?>
                    <span class="ax-pdp__price">
                        <?= number_format((float)$acc['price'], 0, ',', '.') ?><span class="ax-currency">₺</span>
                    </span>
                </div>
                <p class="ax-pdp__price-note">Anlık teslimat · Komisyonsuz · Sigortalı transfer</p>
            </div>

            <div class="ax-pdp__actions">
                <?php if ((int)$acc['sold'] === 1): ?>
                    <button type="button" class="ax-btn ax-btn--secondary ax-btn--lg" disabled>Bu hesap satıldı</button>
                <?php else: ?>
                    <a href="javascript:void(0)"
                       class="ax-btn ax-btn--primary ax-btn--lg ax-pdp__cta"
                       onclick="openTrackedWhatsApp('<?= htmlspecialchars($whatsapp_number) ?>', <?= json_encode($waMsg, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>, 'pdp-buy-<?= (int)$acc['id'] ?>')">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="18" height="18"><path d="M20.5 3.5A11.7 11.7 0 0 0 12 0C5.4 0 0 5.4 0 12c0 2.1.6 4.1 1.6 5.9L0 24l6.3-1.7A11.9 11.9 0 0 0 12 24c6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.5-8.5zM12 21.8c-1.9 0-3.7-.5-5.3-1.5l-.4-.2-3.7 1 1-3.6-.2-.4A9.7 9.7 0 0 1 2.2 12C2.2 6.6 6.6 2.2 12 2.2S21.8 6.6 21.8 12 17.4 21.8 12 21.8z"/></svg>
                        WhatsApp ile Satın Al
                    </a>
                <?php endif; ?>
                <a href="javascript:void(0)"
                   class="ax-btn ax-btn--ghost ax-btn--lg"
                   onclick="openTrackedWhatsApp('<?= htmlspecialchars($whatsapp_number) ?>', <?= json_encode('Merhaba, AXENDELL #'.(int)$acc['id'].' hakkında soru sormak istiyorum.', JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>, 'pdp-ask-<?= (int)$acc['id'] ?>')">
                    Soru Sor
                </a>
            </div>

            <ul class="ax-pdp__perks">
                <li><span>✓</span> Doğrulanmış orijinal hesap</li>
                <li><span>✓</span> 7/24 canlı satış desteği</li>
                <li><span>✓</span> 30 gün satış sonrası garanti</li>
                <li><span>✓</span> Mail + telefon + güvenlik tam erişim</li>
            </ul>
        </aside>
    </div>
</section>

<!-- SPEC TABLE -->
<section class="ax-section ax-section--specs">
    <div class="ax-container">
        <div class="ax-section__head">
            <span class="ax-eyebrow">Hesap özeti</span>
            <h2 class="ax-section__title">Tam teknik döküm</h2>
        </div>

        <div class="ax-pdp__specs ax-reveal">
            <div class="ax-spec"><span class="ax-spec__k">Hesap ID</span><span class="ax-spec__v">#<?= (int)$acc['id'] ?></span></div>
            <div class="ax-spec"><span class="ax-spec__k">Rank</span><span class="ax-spec__v"><?= htmlspecialchars($acc['rank_name'] ?: '—') ?></span></div>
            <div class="ax-spec"><span class="ax-spec__k">Skin Sayısı</span><span class="ax-spec__v"><?= (int)$acc['skins'] ?></span></div>
            <div class="ax-spec"><span class="ax-spec__k">Win Rate</span><span class="ax-spec__v">%<?= number_format((float)$acc['winrate'], 1, ',', '') ?></span></div>
            <div class="ax-spec"><span class="ax-spec__k">Kategori</span><span class="ax-spec__v"><?= htmlspecialchars($acc['category'] ?: 'Genel') ?></span></div>
            <div class="ax-spec"><span class="ax-spec__k">Durum</span><span class="ax-spec__v"><?= ((int)$acc['sold'] === 1) ? 'Satıldı' : 'Satışta' ?></span></div>
            <div class="ax-spec"><span class="ax-spec__k">Teslimat</span><span class="ax-spec__v">Anlık · 5-15 dk</span></div>
            <div class="ax-spec"><span class="ax-spec__k">Garanti</span><span class="ax-spec__v">30 gün</span></div>
        </div>
    </div>
</section>

<?php if (!empty($related)): ?>
<!-- BENZER HESAPLAR -->
<section class="ax-section ax-section--related">
    <div class="ax-container">
        <div class="ax-section__head">
            <span class="ax-eyebrow">Benzer fırsatlar</span>
            <h2 class="ax-section__title">Bunlar da ilgini çekebilir</h2>
        </div>

        <div class="ax-grid ax-grid--cards ax-reveal">
            <?php foreach ($related as $racc):
                $acc = $racc;
                include __DIR__ . '/includes/product-card.php';
            endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php endif; /* if $acc */ ?>

<?php include 'includes/cta.php'; ?>
<?php include 'includes/footer.php'; ?>

<?php if ($acc && !empty($photos)): ?>
<!-- AX Lightbox (Faz 3.D) -->
<div class="ax-lightbox" id="axLightbox" role="dialog" aria-modal="true" aria-label="Fotoğraf görüntüleyici" hidden>
    <button type="button" class="ax-lightbox__close" id="axLbClose" aria-label="Kapat">×</button>
    <button type="button" class="ax-lightbox__nav ax-lightbox__nav--prev" id="axLbPrev" aria-label="Önceki">‹</button>
    <button type="button" class="ax-lightbox__nav ax-lightbox__nav--next" id="axLbNext" aria-label="Sonraki">›</button>
    <div class="ax-lightbox__stage" id="axLbStage">
        <img class="ax-lightbox__img" id="axLbImg" src="" alt="">
    </div>
    <div class="ax-lightbox__counter" id="axLbCounter">1 / <?= count($photos) ?></div>
</div>
<script>window.AX_PHOTOS = <?= json_encode(array_values($photos), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>

<script src="assets/js/app.js" defer></script>
</body>
</html>
