<?php
/**
 * AXENDELL - Veritabanı Tamir Dosyası
 * Bu dosya user_listings tablosundaki eksik sütunları ekler.
 * 
 * KULLANIM: Bu dosyayı sunucuya yükleyin ve tarayıcıdan açın.
 * İşlem bitince GÜVENLİK İÇİN BU DOSYAYI SİLİN!
 */

require_once 'db.php';

$sql = "ALTER TABLE user_listings 
        ADD COLUMN IF NOT EXISTS account_binding VARCHAR(60) DEFAULT NULL AFTER description,
        ADD COLUMN IF NOT EXISTS server_region VARCHAR(60) DEFAULT NULL AFTER account_binding,
        ADD COLUMN IF NOT EXISTS email_changeable TINYINT(1) NOT NULL DEFAULT 0 AFTER server_region,
        ADD COLUMN IF NOT EXISTS negotiable TINYINT(1) NOT NULL DEFAULT 0 AFTER email_changeable";

if ($conn->query($sql) === TRUE) {
    echo "<h1>✅ Başarılı!</h1>";
    echo "<p>Eksik sütunlar başarıyla eklendi. Artık ilan verme hatası düzelmiş olmalı.</p>";
    echo "<p><strong>ÖNEMLİ:</strong> Güvenlik için bu dosyayı (fix_db.php) sunucudan hemen silin!</p>";
} else {
    echo "<h1>❌ Hata!</h1>";
    echo "<p>Sütunlar eklenirken bir hata oluştu: " . $conn->error . "</p>";
}
?>
