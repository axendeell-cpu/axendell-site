<?php
$db_host = 'localhost';
$db_user = 'axendell_admin';
$db_pass = '0@s;mx7=)97hHD$@';
$db_name = 'axendell_admin';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Hesaplar tablosu
$conn->query("CREATE TABLE IF NOT EXISTS `accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `rank_name` VARCHAR(100) NOT NULL,
    `heroes` INT NOT NULL DEFAULT 0,
    `skins` INT NOT NULL DEFAULT 0,
    `emblems` VARCHAR(50) NOT NULL DEFAULT 'MAX',
    `winrate` VARCHAR(20) NOT NULL DEFAULT '%50',
    `level` INT NOT NULL DEFAULT 1,
    `server` VARCHAR(20) NOT NULL DEFAULT 'TR',
    `old_price` DECIMAL(10,2) DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `category` ENUM('evrensel','kuresel_mega','butceli','elmas') NOT NULL DEFAULT 'evrensel',
    `emoji` VARCHAR(10) NOT NULL DEFAULT '⚔️',
    `featured` TINYINT(1) NOT NULL DEFAULT 0,
    `sold` TINYINT(1) NOT NULL DEFAULT 0,
    `image` VARCHAR(500) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Kategori ENUM güncelle (eski: mythic/legend/epic/grandmaster/master → yeni: evrensel/kuresel_mega/butceli)
$conn->query("ALTER TABLE `accounts` MODIFY `category` ENUM('mythic','legend','epic','grandmaster','master','evrensel','kuresel_mega','butceli') NOT NULL DEFAULT 'evrensel'");
$conn->query("UPDATE `accounts` SET `category`='evrensel' WHERE `category` IN ('mythic','grandmaster')");
$conn->query("UPDATE `accounts` SET `category`='kuresel_mega' WHERE `category`='legend'");
$conn->query("UPDATE `accounts` SET `category`='butceli' WHERE `category` IN ('epic','master')");
$conn->query("ALTER TABLE `accounts` MODIFY `category` ENUM('evrensel','kuresel_mega','butceli','elmas') NOT NULL DEFAULT 'evrensel'");

// Admin tablosu
$conn->query("CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ayarlar tablosu
$conn->query("CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Hesap görselleri tablosu (çoklu fotoğraf)
$conn->query("CREATE TABLE IF NOT EXISTS `account_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Varsayılan admin oluştur (admin / admin123)
$admin_check = $conn->query("SELECT id FROM admins LIMIT 1");
if ($admin_check->num_rows === 0) {
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, password) VALUES ('admin', '$hashed')");
}

// Varsayılan ayarlar
$settings = [
    ['whatsapp_number', '905358594798'],
    ['site_title', 'AXENDELL'],
    ['site_description', 'AXENDELL Mobile Legends Hesap Mağazası'],
    ['instagram', '#'],
    ['telegram', '#'],
];

foreach ($settings as $s) {
    $key = $conn->real_escape_string($s[0]);
    $val = $conn->real_escape_string($s[1]);
    $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('$key', '$val')");
}

$conn->close();

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Kurulum</title>
<style>
body{background:#0a0a0a;color:#fff;font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#111;border:1px solid #333;border-radius:20px;padding:40px;text-align:center;max-width:500px;}
h1{color:#FFD700;font-size:28px;margin-bottom:16px;}
p{color:#aaa;margin-bottom:10px;font-size:14px;}
.success{color:#25D366;font-size:16px;font-weight:600;margin:20px 0;}
a{display:inline-block;margin-top:20px;padding:12px 30px;background:linear-gradient(135deg,#FFD700,#DAA520);color:#000;text-decoration:none;border-radius:50px;font-weight:700;transition:all 0.3s;}
a:hover{transform:scale(1.05);box-shadow:0 0 20px rgba(255,215,0,0.4);}
code{background:#222;padding:4px 10px;border-radius:6px;color:#FFD700;font-size:13px;}
</style></head><body>
<div class='box'>
<h1>✅ Kurulum Tamamlandı</h1>
<p class='success'>Veritabanı ve tablolar başarıyla oluşturuldu!</p>
<p>Admin Kullanıcı Adı: <code>admin</code></p>
<p>Admin Şifre: <code>admin123</code></p>
<p style='color:#ff6b6b;margin-top:16px;'>⚠️ Giriş yaptıktan sonra şifrenizi değiştirin!</p>
<a href='admin/'>Admin Panele Git →</a>
</div></body></html>";
?>
