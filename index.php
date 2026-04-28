<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once 'db.php';

function getSettingFront($conn, $key) {
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

$whatsapp_number  = '905358594798';
$site_title       = getSettingFront($conn, 'site_title')       ?: 'AXENDELL';
$site_description = getSettingFront($conn, 'site_description') ?: 'AXENDELL Mobile Legends Hesap Mağazası';
$instagram        = getSettingFront($conn, 'instagram');
$telegram         = getSettingFront($conn, 'telegram');
$whatsapp_message_prefix = "Merhaba, {$site_title}'tan hesap satın almak istiyorum. İlgilendiğim hesap: ";

$maintenance_mode    = getSettingFront($conn, 'maintenance_mode');
$maintenance_text    = getSettingFront($conn, 'maintenance_text') ?: 'Sitemiz şu an bakımda.';
$announcement_active = getSettingFront($conn, 'announcement_active');
$announcement_text   = getSettingFront($conn, 'announcement_text');
$announcement_color  = getSettingFront($conn, 'announcement_color') ?: 'gold';

if ($maintenance_mode === '1') {
    http_response_code(503);
    $wa_no = $whatsapp_number ?? '905358594798';
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex,nofollow">
        <title>Bakım · <?= htmlspecialchars($site_title) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            html, body {
                min-height: 100vh;
                font-family: 'Inter', -apple-system, sans-serif;
                background: #0a0a0a;
                color: #fff;
                overflow-x: hidden;
            }
            
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                background-image:
                    radial-gradient(circle at 20% 30%, rgba(255, 215, 0, 0.08) 0%, transparent 50%),
                    radial-gradient(circle at 80% 70%, rgba(123, 42, 191, 0.1) 0%, transparent 50%);
                background-attachment: fixed;
            }
            
            .bakim-container {
                max-width: 600px;
                width: 100%;
                text-align: center;
                animation: fadeIn 1s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .bakim-icon {
                width: 100px;
                height: 100px;
                margin: 0 auto 30px;
                background: linear-gradient(135deg, rgba(255,215,0,0.15) 0%, rgba(255,165,0,0.08) 100%);
                border: 2px solid rgba(255, 215, 0, 0.3);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                animation: spin 4s linear infinite;
                box-shadow: 0 0 60px rgba(255, 215, 0, 0.15);
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .bakim-rozet {
                display: inline-block;
                background: rgba(255, 215, 0, 0.1);
                border: 1px solid rgba(255, 215, 0, 0.3);
                color: #FFD700;
                padding: 6px 16px;
                border-radius: 100px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.2em;
                margin-bottom: 24px;
                text-transform: uppercase;
            }
            
            .bakim-baslik {
                font-family: 'Playfair Display', serif;
                font-size: clamp(36px, 7vw, 56px);
                font-weight: 700;
                line-height: 1.1;
                margin-bottom: 16px;
                background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #c9a14a 100%);
                -webkit-background-clip: text;
                background-clip: text;
                -webkit-text-fill-color: transparent;
                letter-spacing: -0.02em;
            }
            
            .bakim-mesaj {
                font-size: clamp(15px, 1.6vw, 18px);
                line-height: 1.7;
                color: rgba(255, 255, 255, 0.7);
                margin-bottom: 40px;
                padding: 0 12px;
            }
            
            .bakim-info {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 215, 0, 0.15);
                border-radius: 16px;
                padding: 24px 20px;
                margin-bottom: 32px;
                backdrop-filter: blur(10px);
            }
            
            .bakim-info-baslik {
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.2em;
                color: #FFD700;
                margin-bottom: 12px;
                text-transform: uppercase;
            }
            
            .bakim-info-text {
                font-size: 14px;
                color: rgba(255, 255, 255, 0.8);
                line-height: 1.6;
            }
            
            .whatsapp-btn {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: linear-gradient(135deg, #25d366 0%, #1ea952 100%);
                color: #fff;
                padding: 16px 32px;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 700;
                text-decoration: none;
                transition: all 0.3s;
                box-shadow: 0 8px 24px rgba(37, 211, 102, 0.3);
                margin-bottom: 24px;
            }
            
            .whatsapp-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 32px rgba(37, 211, 102, 0.4);
                background: linear-gradient(135deg, #2ee574 0%, #25d366 100%);
            }
            
            .whatsapp-btn svg {
                width: 18px;
                height: 18px;
            }
            
            .bakim-foot {
                margin-top: 40px;
                padding-top: 24px;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                font-size: 12px;
                color: rgba(255, 255, 255, 0.4);
                letter-spacing: 0.05em;
            }
            
            .bakim-logo {
                font-family: 'Playfair Display', serif;
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 0.3em;
                color: #FFD700;
                margin-bottom: 8px;
            }
            
            @media (max-width: 480px) {
                body { padding: 24px 16px; }
                .bakim-icon { width: 80px; height: 80px; font-size: 36px; margin-bottom: 24px; }
                .bakim-baslik { font-size: 32px; }
                .bakim-mesaj { font-size: 14px; padding: 0; }
                .bakim-info { padding: 18px 16px; }
                .bakim-info-text { font-size: 13px; }
                .whatsapp-btn { width: 100%; justify-content: center; padding: 14px 24px; font-size: 14px; }
            }
        </style>
    </head>
    <body>
        <div class="bakim-container">
            <div class="bakim-icon">🛠️</div>
            <div class="bakim-rozet">BAKIM MODU</div>
            <h1 class="bakim-baslik">Çok yakında<br>geri döneceğiz</h1>
            <p class="bakim-mesaj"><?= nl2br(htmlspecialchars($maintenance_text)) ?></p>
            
            <div class="bakim-info">
                <div class="bakim-info-baslik">⚡ ACİL DURUM</div>
                <div class="bakim-info-text">
                    Acil bir konu için bize WhatsApp üzerinden ulaşabilirsiniz. Anında dönüş yapıyoruz.
                </div>
            </div>
            
            <a href="https://wa.me/<?= htmlspecialchars($wa_no) ?>?text=<?= urlencode('Merhaba, sitenizde bakım modu var. Acil bir konuda yardım alabilir miyim?') ?>" target="_blank" class="whatsapp-btn">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                WhatsApp ile İletişim
            </a>
            
            <div class="bakim-foot">
                <div class="bakim-logo"><?= htmlspecialchars($site_title) ?></div>
                <div>© <?= date('Y') ?> · Tüm hakları saklıdır</div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$accounts = [];
// account_clicks tablosundan görüntülenme sayılarını join ile çekiyoruz
$sql = "SELECT a.id, a.title, a.price, a.old_price, a.image, a.rank_name, a.skins, a.winrate, a.sold, a.category, a.featured, 
               (SELECT COUNT(*) FROM account_clicks ac WHERE ac.account_id = a.id) as view_count
        FROM accounts a 
        ORDER BY a.sort_order ASC, a.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $title, $price, $old_price, $image, $rank_name, $skins, $winrate, $sold, $category, $featured, $view_count);
while ($stmt->fetch()) {
    $accounts[] = compact('id','title','price','old_price','image','rank_name','skins','winrate','sold','category','featured', 'view_count');
}
$stmt->close();

$gallery = [];
$img_result = @$conn->query("SELECT account_id, image_path FROM account_images ORDER BY sort_order ASC");
if ($img_result) {
    while ($img = mysqli_fetch_assoc($img_result)) {
        $gallery[$img['account_id']][] = $img['image_path'];
    }
}

$r = mysqli_fetch_assoc($conn->query("SELECT COUNT(*) as c FROM accounts WHERE sold = 1"));
$total_sold = (int)($r['c'] ?? 0);
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AXENDELL | Mobile Legends Hesap Satışı — Premium MLBB Marketplace</title>
    <meta name="description" content="Türkiye'nin premium Mobile Legends hesap pazarı. Doğrulanmış MLBB hesapları, anlık teslimat ve 7/24 WhatsApp desteği.">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    <link rel="canonical" href="https://www.axendell.com">
    <meta name="theme-color" content="#0B0B0F">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.axendell.com">
    <meta property="og:title" content="AXENDELL | Mobile Legends Hesap Satışı">
    <meta property="og:description" content="Türkiye'nin premium MLBB hesap pazarı.">
    <meta property="og:image" content="https://www.axendell.com/uploads/og-banner.jpg">
    <meta property="og:site_name" content="AXENDELL">
    <meta property="og:locale" content="tr_TR">

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

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "Organization",
          "@id": "https://www.axendell.com/#organization",
          "name": "AXENDELL",
          "url": "https://www.axendell.com",
          "logo": "https://www.axendell.com/uploads/logo.png",
          "description": "Türkiye'nin premium Mobile Legends hesap pazarı.",
          "sameAs": [
            <?php $social = []; if (!empty($instagram)) $social[] = '"' . htmlspecialchars($instagram) . '"'; if (!empty($telegram)) $social[] = '"' . htmlspecialchars($telegram) . '"'; echo implode(',', $social); ?>
          ]
        },
        {
          "@type": "WebSite",
          "@id": "https://www.axendell.com/#website",
          "url": "https://www.axendell.com",
          "name": "AXENDELL",
          "inLanguage": "tr-TR",
          "publisher": { "@id": "https://www.axendell.com/#organization" }
        },
        {
          "@type": "WebSite",
          "@id": "https://www.axendell.com/#website",
          "url": "https://www.axendell.com",
          "name": "AXENDELL",
          "inLanguage": "tr-TR",
          "publisher": { "@id": "https://www.axendell.com/#organization" }
        },
        {
          "@type": "Store",
          "@id": "https://www.axendell.com/#store",
          "name": "AXENDELL",
          "image": "https://www.axendell.com/uploads/og-banner.jpg",
          "url": "https://www.axendell.com",
          "priceRange": "₺₺",
          "telephone": "+<?= preg_replace('/\D/', '', $whatsapp_number) ?>",
          "address": { "@type": "PostalAddress", "addressCountry": "TR" }
        }
      ]
    }
    </script>
</head>
<body>

<div class="ax-progress" aria-hidden="true"><span class="ax-progress__bar"></span></div>

<?php include 'includes/header.php'; ?>
<?php include 'includes/hero.php'; ?>

<section class="ax-section" id="accounts">
    <div class="ax-container">
        <?php include 'includes/category-chips.php'; ?>
        <?php include 'includes/product-grid.php'; ?>
    </div>
</section>

<?php include 'includes/features.php'; ?>
<?php include 'includes/trust.php'; ?>
<?php include 'includes/testimonials.php'; ?>
<?php include 'includes/faq.php'; ?>
<?php include 'includes/cta.php'; ?>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js" defer></script>
</body>
</html>
