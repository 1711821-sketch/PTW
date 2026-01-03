# PTW Deployment Guide

## Quick Start (Webdock Server)

### 1. SSH til serveren
```bash
ssh admin@193.181.211.144
```
Password: ZenNGhDbPydW

### 2. Kør installation
```bash
cd /tmp
wget https://raw.githubusercontent.com/1711821-sketch/PTW/main/deploy/install.sh
sudo bash install.sh
```

Eller manuelt:
```bash
sudo git clone https://github.com/1711821-sketch/PTW.git /var/www/ptw
cd /var/www/ptw/deploy
sudo bash install.sh
```

## Manuel Installation

### Trin 1: Opret web-mappe
```bash
sudo mkdir -p /var/www/ptw
sudo chown -R www-data:www-data /var/www/ptw
```

### Trin 2: Klon repository
```bash
sudo git clone https://github.com/1711821-sketch/PTW.git /var/www/ptw
```

### Trin 3: Opret MySQL database
```bash
mysql -u admin -pyHv9vbhE49pW << EOF
CREATE DATABASE ptw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ptw_user'@'localhost' IDENTIFIED BY 'DitPassword123';
GRANT ALL PRIVILEGES ON ptw.* TO 'ptw_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### Trin 4: Importer schema
```bash
mysql -u ptw_user -pDitPassword123 ptw < /var/www/ptw/deploy/schema-mysql.sql
```

### Trin 5: Opret config/local.env
```bash
sudo nano /var/www/ptw/config/local.env
```

Indhold:
```
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ptw
DB_USER=ptw_user
DB_PASSWORD=DitPassword123

OPENAI_API_KEY=sk-din-api-noegle-her
```

### Trin 6: Erstat database.php
```bash
sudo cp /var/www/ptw/deploy/database-mysql.php /var/www/ptw/database.php
```

### Trin 7: Opsæt Nginx
```bash
sudo cp /var/www/ptw/deploy/nginx-ptw.conf /etc/nginx/sites-available/ptw
sudo ln -sf /etc/nginx/sites-available/ptw /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Trin 8: SSL certifikat
```bash
sudo certbot --nginx -d ptw.interterminals.app
```

### Trin 9: Sæt permissions
```bash
sudo chown -R www-data:www-data /var/www/ptw
sudo chmod 600 /var/www/ptw/config/local.env
```

## GitHub Auto-Deploy

### Opsæt Webhook
1. Gå til GitHub repo -> Settings -> Webhooks -> Add webhook
2. **Payload URL:** `https://ptw.interterminals.app/deploy/webhook.php`
3. **Content type:** `application/json`
4. **Secret:** Generer en sikker hemmelighed
5. **Events:** Push events only

### Opdater webhook secret
Rediger `/var/www/ptw/deploy/webhook.php` og ændr:
```php
define('WEBHOOK_SECRET', 'din-hemmelige-noegle');
```

### Test webhook
```bash
tail -f /var/log/ptw-deploy.log
```

## Troubleshooting

### Database forbindelse fejler
```bash
# Test MySQL forbindelse
mysql -u ptw_user -pDitPassword123 -e "SELECT 1"

# Tjek PHP MySQL extension
php -m | grep mysql
```

### Permission errors
```bash
sudo chown -R www-data:www-data /var/www/ptw
sudo find /var/www/ptw -type f -exec chmod 644 {} \;
sudo find /var/www/ptw -type d -exec chmod 755 {} \;
```

### Nginx fejl
```bash
sudo nginx -t
sudo tail -f /var/log/nginx/ptw.error.log
```

### PHP fejl
```bash
sudo tail -f /var/log/php8.3-fpm.log
```

## Login

**Standard login:**
- Brugernavn: `admin`
- Password: `admin123`

**VIGTIGT:** Skift password efter første login!
