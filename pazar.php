<?php
/**
 * /pazar.php
 * Public marketplace - tum onayli uye ilanlarini listeler.
 */
require_once __DIR__ . '/db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Filtre parametreleri
$q       = trim($_GET['q'] ?? '');
$min     = (int)($_GET['min'] ?? 0);
$max     = (int)($_GET['max'] ?? 0);
$rank    = trim($_GET['rank'] ?? '');
$sort    = $_GET['sort'] ?? 'new';
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 12;

// WHERE clause
$where = "l.status = 'approved'";
$types = '';
$params = [];

if ($q !== '') {
    $where .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.rank_name LIKE ?)";
    $like = '%' . $q . '%';
    $types .= 'sss';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($min > 0) { $where .= " AND l.price >= ?"; $types .= 'i'; $params[] = $min; }
if ($max > 0) { $where .= " AND l.price <= ?"; $types .= 'i'; $params[] = $max; }
if ($rank !== '') { $where .= " AND l.rank_name LIKE ?"; $types .= 's'; $params[] = '%' . $rank . '%'; }

// Sıralama
$orderBy = "l.approved_at DESC, l.id DESC";
if ($sort === 'price_asc')  $orderBy = "l.price ASC, l.id DESC";
if ($sort === 'price_desc') $orderBy = "l.price DESC, l.id DESC";
if ($sort === 'popular')    $orderBy = "l.view_count DESC, l.id DESC";

// Toplam sayı
$sqlCount = "SELECT COUNT(*) FROM user_listings l WHERE $where";
$stmt = $conn->prepare($sqlCount);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch(); $stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Listeleme
$sql = "SELECT l.id, l.title, l.price, l.image, l.rank_name, l.skins, l.winrate,
               l.view_count, l.approved_at, u.name as seller_name, u.rating_avg, u.total_sales
        FROM user_listings l
        JOIN users u ON u.id = l.user_id
        WHERE $where
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$stmt->bind_result($id,$title,$price,$image,$rname,$skins,$wr,$views,$approved,$sname,$rating,$tsales);
$listings = [];
while ($stmt->fetch()) {
    $listings[] = compact('id','title','price','image','rname','skins','wr','views','approved','sname','rating','tsales');
}
$stmt->close();

// Distinct ranks for filter
$rank_options = [];
$res = $conn->query("SELECT DISTINCT rank_name FROM user_listings WHERE status='approved' AND rank_name<>'' ORDER BY rank_name");
if ($res) while ($r = $res->fetch_assoc()) $rank_options[] = $r['rank_name'];

// Logo
$logo = '/uploads/logo.png';
$site_name = 'AXENDELL';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Üye İlanları Pazarı - Mobile Legends Hesap Satın Al | Axendell</title>
<meta name="description" content="Doğrulanmış üyelerden Mobile Legends hesabı satın alın. Tüm ilanlar admin onayından geçer.">
<link rel="icon" href="<?= h($logo) ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0a0a0a;color:#fff;min-height:100vh;
background-image:radial-gradient(circle at 20% 0%, rgba(123,42,191,0.15) 0%, transparent 50%),
                  radial-gradient(circle at 80% 100%, rgba(255,215,0,0.08) 0%, transparent 50%);}
a{color:inherit;text-decoration:none}
.container{max-width:1280px;margin:0 auto;padding:24px}

/* Header */
.hdr{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;background:rgba(10,10,10,0.85);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,215,0,0.12);position:sticky;top:0;z-index:100}
.hdr .brand{display:flex;align-items:center;gap:12px;font-family:'Playfair Display',serif;font-size:22px;color:#FFD700}
.hdr .brand img{width:36px;height:36px;border-radius:8px;background:#000;padding:4px;border:1px solid rgba(255,215,0,0.2)}
.hdr nav{display:flex;gap:24px;align-items:center}
.hdr nav a{font-size:14px;color:#bbb;font-weight:500;transition:color 0.15s}
.hdr nav a:hover, .hdr nav a.active{color:#FFD700}
.hdr .cta{padding:8px 18px;background:#FFD700;color:#000;border-radius:8px;font-weight:600;font-size:13px}
.hdr .cta:hover{background:#FFA500}
@media(max-width:760px){.hdr nav{display:none}}

/* Hero */
.hero{padding:48px 0 24px;text-align:center}
.hero h1{font-family:'Playfair Display',serif;font-size:42px;background:linear-gradient(135deg,#FFD700,#FFA500);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:12px}
.hero p{color:#999;font-size:15px;max-width:640px;margin:0 auto 8px}
.hero .badges{display:flex;justify-content:center;gap:18px;margin-top:18px;flex-wrap:wrap}
.hero .badges span{padding:6px 14px;border:1px solid rgba(255,215,0,0.3);border-radius:99px;font-size:12px;color:#FFD700}

/* Filters - Daraltlmış, daha az ön planda */
.filters-toggle{display:flex;justify-content:flex-end;margin-bottom:10px}
.filters-toggle button{padding:7px 14px;background:#161616;border:1px solid #2a2a2a;border-radius:8px;color:#999;font-size:12px;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.filters-toggle button:hover{border-color:#FFD700;color:#FFD700}
.filters{background:#0d0d0d;border:1px solid rgba(255,255,255,0.04);border-radius:12px;padding:12px;margin-bottom:18px;display:none;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;align-items:end}
.filters.open{display:grid}
@media(max-width:900px){.filters.open{grid-template-columns:1fr 1fr;gap:8px}}
.filters label{display:block;font-size:10px;color:#666;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
.filters input,.filters select{width:100%;padding:8px 10px;background:#0a0a0a;border:1px solid #222;border-radius:7px;color:#ccc;font-size:12px;font-family:inherit;outline:none}
.filters input:focus,.filters select:focus{border-color:#FFD700;color:#fff}
.filters button[type=submit]{padding:8px 16px;background:#FFD700;color:#000;border:none;border-radius:7px;font-weight:600;cursor:pointer;font-size:12px;font-family:inherit;height:33px}
.filters button[type=submit]:hover{background:#FFA500}

/* Sayım + Sıralama satırı */
.toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px}
.toolbar .count{color:#999;font-size:14px}
.toolbar .count b{color:#FFD700}
.toolbar .sort-tabs{display:flex;gap:6px;flex-wrap:wrap}
.toolbar .sort-tabs a{padding:7px 14px;background:#161616;border:1px solid #2a2a2a;border-radius:8px;font-size:12px;color:#bbb}
.toolbar .sort-tabs a:hover{border-color:#FFD700;color:#FFD700}
.toolbar .sort-tabs a.active{background:linear-gradient(135deg,rgba(255,215,0,0.18),rgba(255,165,0,0.08));border-color:#FFD700;color:#FFD700;font-weight:600}

/* Grid */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px}
.card{background:linear-gradient(145deg,#141414,#0d0d0d);border:1px solid rgba(255,255,255,0.06);border-radius:14px;overflow:hidden;transition:all 0.2s;display:flex;flex-direction:column}
.card:hover{transform:translateY(-3px);border-color:rgba(255,215,0,0.4);box-shadow:0 10px 28px rgba(255,215,0,0.08)}
.card .thumb{position:relative;aspect-ratio:4/3;background:#000;overflow:hidden}
.card .thumb img{width:100%;height:100%;object-fit:cover;transition:transform 0.3s}
.card:hover .thumb img{transform:scale(1.05)}
.card .thumb .empty{display:flex;align-items:center;justify-content:center;height:100%;color:#333;font-size:48px}
.card .thumb .price{position:absolute;bottom:10px;right:10px;padding:6px 14px;background:rgba(255,215,0,0.95);color:#000;border-radius:8px;font-weight:700;font-size:14px}
.card .thumb .verified{position:absolute;top:10px;left:10px;padding:4px 10px;background:rgba(37,211,102,0.9);color:#000;border-radius:99px;font-size:10px;font-weight:700;letter-spacing:0.5px}
.card .body{padding:14px;flex:1;display:flex;flex-direction:column}
.card .body h3{font-size:14px;font-weight:600;color:#fff;margin-bottom:8px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:36px}
.card .specs{display:flex;gap:10px;font-size:11px;color:#888;flex-wrap:wrap;margin-bottom:10px}
.card .specs span{display:flex;align-items:center;gap:4px}
.card .seller{padding-top:10px;border-top:1px solid #1a1a1a;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#777}
.card .seller .name{color:#bbb;font-weight:500}
.card .seller .stars{color:#FFD700}

/* Pagination */
.pagination{display:flex;justify-content:center;gap:6px;margin:36px 0 20px;flex-wrap:wrap}
.pagination a, .pagination span{padding:9px 14px;background:#161616;border:1px solid #2a2a2a;border-radius:8px;font-size:13px;color:#bbb;min-width:42px;text-align:center}
.pagination a:hover{border-color:#FFD700;color:#FFD700}
.pagination .current{background:#FFD700;color:#000;border-color:#FFD700;font-weight:600}
.pagination .gap{border:none;background:transparent;color:#444}

/* Empty */
.empty-state{text-align:center;padding:80px 24px;background:linear-gradient(145deg,#141414,#0d0d0d);border:1px solid rgba(255,255,255,0.06);border-radius:16px}
.empty-state .icon{font-size:72px;margin-bottom:18px;opacity:0.3}
.empty-state h2{color:#FFD700;font-family:'Playfair Display',serif;margin-bottom:10px}
.empty-state p{color:#999;font-size:14px}

/* Footer */
footer{margin-top:60px;padding:30px 24px;text-align:center;color:#555;font-size:12px;border-top:1px solid rgba(255,255,255,0.05)}
footer a{color:#888;margin:0 10px}
footer a:hover{color:#FFD700}

/* Policy Modal */
.policy-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;align-items:flex-start;justify-content:center;padding:40px 16px;overflow-y:auto;backdrop-filter:blur(4px)}
.policy-modal-bg.show{display:flex}
.policy-modal{background:#1a1a1a;border:1px solid rgba(255,215,0,0.2);border-radius:16px;max-width:820px;width:100%;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.6);margin:auto}
.policy-close{position:absolute;top:14px;right:14px;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.08);border:none;color:#fff;font-size:24px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;transition:background 0.2s;z-index:2}
.policy-close:hover{background:rgba(255,215,0,0.2);color:#FFD700}
.policy-header{padding:28px 32px 20px;border-bottom:1px solid rgba(255,215,0,0.15);background:linear-gradient(135deg,rgba(255,215,0,0.05),transparent);border-radius:16px 16px 0 0}
.policy-header h2{margin:0;color:#FFD700;font-size:20px;font-weight:700;padding-right:40px}
.policy-body{padding:24px 32px 32px;color:#ccc;font-size:14px;line-height:1.7}
.policy-body h3{color:#FFD700;font-size:16px;margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid rgba(255,215,0,0.1)}
.policy-body h3:first-child{margin-top:0}
.policy-body p{margin:10px 0}
.policy-body ul{margin:10px 0;padding-left:22px}
.policy-body ul li{margin:8px 0}
.policy-body b{color:#fff}
.policy-table{width:100%;border-collapse:collapse;margin:14px 0;background:rgba(255,255,255,0.02);border-radius:8px;overflow:hidden;font-size:13px}
.policy-table th{background:rgba(255,215,0,0.08);color:#FFD700;text-align:left;padding:10px 12px;font-weight:600;border-bottom:1px solid rgba(255,215,0,0.15)}
.policy-table td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,0.05);vertical-align:top}
.policy-table tr:last-child td{border-bottom:none}
.policy-note{background:rgba(255,215,0,0.06);border-left:3px solid #FFD700;padding:12px 14px;margin:14px 0;border-radius:6px;font-size:13px;color:#ddd}
.policy-note b{color:#FFD700}
.policy-footer{margin-top:24px;padding-top:18px;border-top:1px solid rgba(255,215,0,0.15);font-size:12px;color:#888;font-style:italic;text-align:center}
@media (max-width:600px){
    .policy-modal-bg{padding:16px 8px}
    .policy-header{padding:22px 20px 16px}
    .policy-header h2{font-size:17px}
    .policy-body{padding:18px 20px 24px;font-size:13px}
    .policy-table{font-size:12px}
    .policy-table th,.policy-table td{padding:8px}
}
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
    <a href="/uye/giris.php" class="cta">Sat / Giriş</a>
</header>

<div class="container">

    <section class="hero">
        <h1>Üye İlanları Pazarı</h1>
        <p>Üyelerimiz tarafından satışa sunulan Mobile Legends hesapları. AXENDELL bu ilanların <b style="color:#FFD700">aracısıdır</b>.</p>

        <div style="margin-top:18px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap">
            <button type="button" onclick="document.getElementById('policyModal').classList.add('show')" style="padding:10px 20px;background:transparent;color:#FFD700;border:1px solid rgba(255,215,0,0.5);border-radius:99px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:all 0.2s">
                📋 Sorumluluk Sınırları ve Garanti Politikası
            </button>
        </div>
    </section>

    <!-- POLICY MODAL -->
    <div id="policyModal" class="policy-modal-bg" onclick="if(event.target===this)this.classList.remove('show')">
        <div class="policy-modal">
            <button class="policy-close" onclick="document.getElementById('policyModal').classList.remove('show')">×</button>
            <div class="policy-header">
                <h2>AXENDELL Sorumluluk Sınırları ve Garanti Politikası</h2>
                <p style="color:#999;font-size:13px;margin-top:6px">Üye Pazarı işlemleri için yasal bilgilendirme</p>
            </div>
            <div class="policy-body">

                <h3>1. Garanti Kapsamı ve Farklılıklar</h3>
                <p>Platformumuz üzerinden sunulan hesaplar, iki farklı kategoriye ayrılır. Bu ayrım, sorumluluk ve garanti koşullarını doğrudan etkiler.</p>
                <table class="policy-table">
                    <thead><tr><th>Kategori</th><th>Kefil Durumu</th><th>Açıklama</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><b>AXENDELL Koleksiyonu</b></td>
                            <td><span style="color:#25D366;font-weight:600">✅ Tam Kefil</span></td>
                            <td>AXENDELL tarafından doğrudan temin edilen ve kontrolü sağlanan hesaplardır. Bu hesaplar için AXENDELL tam sorumluluk üstlenir.</td>
                        </tr>
                        <tr>
                            <td><b>Üye Pazarı (Kullanıcı İlanları)</b></td>
                            <td><span style="color:#FFD700;font-weight:600">⚠️ Satıcı Kefil</span></td>
                            <td>Platform üyeleri tarafından yayınlanan ilanlardır. Hesabın orijinalliği, geçmişi ve güvenilirliği konusunda kefil satıcının kendisidir.</td>
                        </tr>
                    </tbody>
                </table>

                <h3>2. AXENDELL'in Rolü ve Taahhütleri</h3>
                <p>Platform olarak üstlendiğimiz sorumluluklar, işlem öncesi ve işlem anına odaklanmıştır:</p>
                <ul>
                    <li><b>Filtreleme ve Doğrulama:</b> Dolandırıcı hesapları, şüpheli aktiviteleri ve güvenilirliği tartışmalı profilleri önleyici algoritmalar ve manuel inceleme süreçleriyle tespit edip platformdan uzaklaştırma</li>
                    <li><b>Güvenli Aracılık:</b> Alıcı ve satıcı arasında tarafsız, şeffaf ve güvenli bir işlem ortamı sağlama</li>
                    <li><b>Ödeme Güvenliği:</b> Finansal süreçlerin korunaklı altyapı üzerinden yönetilmesi, alıcının ödemesinin güvence altına alınması ve satıcının hak edişinin zamanında ödenmesi</li>
                    <li><b>Teslimat Kontrolü:</b> Hesap bilgilerinin eksiksiz, doğru ve anlaşılan şekilde devredilmesinin takibi ve onayı</li>
                </ul>

                <h3>3. Üye Pazarının Amaç ve Kapsamı</h3>
                <p>AXENDELL, üye pazarını yalnızca aşağıdaki amaçlar doğrultusunda açmıştır:</p>
                <ul>
                    <li><b>Satıcılar için:</b> Sahip oldukları hesapları güvenilir bir platform üzerinden potansiyel alıcılara ulaştırma imkanı sunmak</li>
                    <li><b>Alıcılar için:</b> AXENDELL koleksiyonunda yer almayan, kendi ihtiyaç ve tercihlerine özel hesapları kolayca bulma ve satın alma olanağı sağlamak</li>
                </ul>
                <p>Üye pazarı, AXENDELL'in doğrudan ürün veya hizmet sunduğu bir mağaza değil; kullanıcılarımızın birbirleriyle güvenli şekilde buluşmasını kolaylaştıran bir aracı platform olarak işlev görür.</p>

                <h3>4. Sorumluluk Sınırı — İşlem Sonrası Süreçler</h3>
                <p>AXENDELL, aşağıdaki durumların hiçbirinde hukuki veya mali sorumluluk kabul etmez:</p>
                <table class="policy-table">
                    <thead><tr><th>Durum</th><th>Açıklama</th></tr></thead>
                    <tbody>
                        <tr><td><b>Hesabın Geri Alınması</b></td><td>İşlem tamamlandıktan sonra hesabın orijinal sahibi, üçüncü bir taraf veya oyun/platform sağlayıcısı tarafından geri alınması, askıya alınması veya kapatılması</td></tr>
                        <tr><td><b>Şifre ve Erişim Değişiklikleri</b></td><td>Satın alınan hesabın şifresinin değiştirilmesi, iki faktörlü doğrulama bilgilerinin güncellenmesi veya hesaba erişimin kaybedilmesi</td></tr>
                        <tr><td><b>Satıcı-Alıcı Uyuşmazlıkları</b></td><td>Satıcı ile alıcı arasında doğabilecek her türlü anlaşmazlık, ihtilaf, yanlış anlaşılma veya hukuki uyuşmazlık</td></tr>
                    </tbody>
                </table>
                <div class="policy-note"><b>Önemli Not:</b> İşlem tamamlanması, AXENDELL'in aracılık ve kontrol sürecinin sona erdiği anlamına gelir. Bu tarihten itibaren hesapla ilgili tüm risk, kullanım ve yönetim sorumluluğu tamamen alıcıya aittir.</div>

                <h3>5. Satıcı Kimlik Bilgilerinin Saklanması ve Paylaşımı</h3>
                <p>AXENDELL, üye pazarı işlemlerinin güvenliği ve tarafların hukuki haklarının korunması amacıyla aşağıdaki politikayı uygular:</p>
                <ul>
                    <li><b>Kimlik Bilgilerinin Kaydı:</b> Üye pazarı üzerinden satış yapan kullanıcıların, ödeme sürecinde T.C. Kimlik Numarası ve benzeri kimlik bilgileri sistemimizde kayıt altına alınmaktadır.</li>
                    <li><b>Bilgi Paylaşımı:</b> Satıcının işlem sonrasında hesabı geri çekmesi, şifre değiştirmesi veya benzeri bir ihlalde bulunması halinde; alıcı, kendi adına yasal işlem başlatmak veya satıcıya ulaşmak amacıyla bu kimlik bilgilerinin kendisine iletilmesini talep edebilir.</li>
                    <li><b>Talep Süreci:</b> Alıcının yasal mercilere başvurması veya doğrudan satıcıya ulaşması gerektiği durumlarda, AXENDELL ilgili kimlik bilgilerini alıcıya yasal talep doğrultusunda iletecektir.</li>
                </ul>
                <div class="policy-note"><b>Amaç:</b> Bu uygulama, AXENDELL'in satış sonrası sorumluluk üstlenmediği durumlarda dahi, alıcının yasal yollara başvurabilmesi için gerekli bilgilere erişimini kolaylaştırmak amacıyla hayata geçirilmiştir.</div>

                <h3>6. Üye Pazarı Alıcıları İçin Önemli Hatırlatmalar</h3>
                <p>Üye pazarından hesap satın alırken aşağıdaki hususları göz önünde bulundurmanızı önemle tavsiye ederiz:</p>
                <ul>
                    <li><b>Satıcı Profili:</b> Satıcının platformdaki geçmişini, varsa diğer ilanlarını ve puanlamalarını inceleyin.</li>
                    <li><b>İletişim:</b> İşlem öncesinde aklınıza takılan tüm soruları satıcıya yöneltin ve gerekli teyitleri alın.</li>
                    <li><b>Risk Bilinci:</b> Üye pazarı ilanlarının, AXENDELL koleksiyonu ilanlarına göre daha yüksek risk taşıyabileceğini ve bu riskin tamamen alıcıya ait olduğunu unutmayın.</li>
                </ul>

                <div class="policy-footer">
                    Bu politika, AXENDELL platformunu kullanan tüm alıcı ve satıcılar için bağlayıcıdır. İşlem başlatan her kullanıcı, bu şartları okumuş ve kabul etmiş sayılır.
                </div>
            </div>
        </div>
    </div>

    <div class="filters-toggle">
        <button type="button" onclick="document.querySelector('.filters').classList.toggle('open')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9v6l4 2v-8l8-9z"/></svg>
            Filtreleri Göster
        </button>
    </div>

    <form class="filters <?= ($q||$min||$max||$rank) ? 'open' : '' ?>">
        <div>
            <label>Arama</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="İlan başlığı, kahraman...">
        </div>
        <div>
            <label>Min Fiyat</label>
            <input type="number" name="min" value="<?= $min ?: '' ?>" placeholder="0">
        </div>
        <div>
            <label>Max Fiyat</label>
            <input type="number" name="max" value="<?= $max ?: '' ?>" placeholder="100.000">
        </div>
        <div>
            <label>Rank</label>
            <select name="rank">
                <option value="">Tümü</option>
                <?php foreach($rank_options as $ro): ?>
                    <option value="<?= h($ro) ?>" <?= $rank===$ro?'selected':'' ?>><?= h($ro) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Uygula</button>
    </form>

    <div class="toolbar">
        <div class="count">Toplam <b><?= $total ?></b> ilan bulundu</div>
        <div class="sort-tabs">
            <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'new'])) ?>" class="<?= $sort==='new'?'active':'' ?>">En Yeni</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'popular'])) ?>" class="<?= $sort==='popular'?'active':'' ?>">Popüler</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'price_asc'])) ?>" class="<?= $sort==='price_asc'?'active':'' ?>">En Düşük Fiyat</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'price_desc'])) ?>" class="<?= $sort==='price_desc'?'active':'' ?>">En Yüksek Fiyat</a>
        </div>
    </div>

    <?php if ($total > 0): ?>
        <div class="grid">
            <?php foreach ($listings as $l): ?>
                <a href="pazar-ilan.php?id=<?= $l['id'] ?>" class="card">
                    <div class="thumb">
                        <?php if ($l['image']): ?>
                            <img src="<?= h($l['image']) ?>" alt="<?= h($l['title']) ?>">
                        <?php else: ?>
                            <div class="empty">🖼️</div>
                        <?php endif; ?>
                        <div class="verified">✓ ONAYLI</div>
                        <div class="price"><?= number_format($l['price'], 0, ',', '.') ?> ₺</div>
                    </div>
                    <div class="body">
                        <h3><?= h($l['title']) ?></h3>
                        <div class="specs">
                            <span>🏆 <?= h($l['rname']) ?></span>
                            <span>⚔️ <?= $l['skins'] ?> skin</span>
                            <span>📊 %<?= number_format($l['wr'], 1, ',', '') ?></span>
                        </div>
                        <div class="seller">
                            <div class="name">👤 <?= h($l['seller_name']) ?></div>
                            <div class="views">👁 <?= number_format($l['views']) ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i=1; $i<=$totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p'=>$i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div class="icon">🔍</div>
            <h2>İlan Bulunamadı</h2>
            <p>Arama kriterlerinize uygun ilan bulunmuyor. Lütfen filtreleri temizleyip tekrar deneyin.</p>
            <a href="pazar.php" style="display:inline-block;margin-top:20px;color:#FFD700;font-weight:600">Tüm İlanları Gör →</a>
        </div>
    <?php endif; ?>

    <footer>
        &copy; <?= date('Y') ?> <?= h($site_name) ?> — Tüm hakları saklıdır.
        <br><br>
        <a href="/">Ana Sayfa</a>
        <a href="/pazar.php">Üye Pazarı</a>
        <a href="/uye/panel.php">Üye Paneli</a>
    </footer>

</div>

</body>
</html>
