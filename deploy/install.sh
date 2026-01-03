#!/bin/bash
# PTW Deployment Script for Webdock (Noble LEMP 8.3)
# Run as sudo: sudo bash install.sh

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== PTW Deployment Script ===${NC}"
echo ""

# Configuration
DOMAIN="ptw.interterminals.app"
WEB_ROOT="/var/www/ptw"
GITHUB_REPO="https://github.com/1711821-sketch/PTW.git"
DB_NAME="ptw"
DB_USER="ptw_user"
DB_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 16)

# MySQL credentials from Webdock
MYSQL_ROOT_USER="admin"
MYSQL_ROOT_PASS="yHv9vbhE49pW"

echo -e "${YELLOW}Step 1: Creating web directory${NC}"
mkdir -p $WEB_ROOT
chown -R www-data:www-data $WEB_ROOT

echo -e "${YELLOW}Step 2: Cloning repository${NC}"
if [ -d "$WEB_ROOT/.git" ]; then
    echo "Repository exists, pulling latest..."
    cd $WEB_ROOT
    git pull origin main
else
    git clone $GITHUB_REPO $WEB_ROOT
fi
cd $WEB_ROOT

echo -e "${YELLOW}Step 3: Creating MySQL database${NC}"
mysql -u $MYSQL_ROOT_USER -p$MYSQL_ROOT_PASS << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

echo -e "${YELLOW}Step 4: Importing database schema${NC}"
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $WEB_ROOT/deploy/schema-mysql.sql

echo -e "${YELLOW}Step 5: Creating environment file${NC}"
cat > $WEB_ROOT/config/local.env << EOF
# Database Configuration (MySQL)
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASS

# OpenAI API key for Voice Assistant (add your key here)
OPENAI_API_KEY=
EOF

echo -e "${YELLOW}Step 6: Replacing database.php with MySQL version${NC}"
cp $WEB_ROOT/deploy/database-mysql.php $WEB_ROOT/database.php

echo -e "${YELLOW}Step 7: Setting up Nginx${NC}"
cp $WEB_ROOT/deploy/nginx-ptw.conf /etc/nginx/sites-available/ptw
ln -sf /etc/nginx/sites-available/ptw /etc/nginx/sites-enabled/ptw

# Test nginx config
nginx -t

echo -e "${YELLOW}Step 8: Obtaining SSL certificate${NC}"
certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email admin@interterminals.app || {
    echo -e "${RED}SSL certificate failed. Setting up without SSL for now.${NC}"
    # Create non-SSL config
    cat > /etc/nginx/sites-available/ptw << 'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name ptw.interterminals.app;

    root /var/www/ptw;
    index index.php index.html;

    location ~ /\.(git|env|htaccess) { deny all; return 404; }
    location ~ ^/config/(local\.env) { deny all; return 404; }
    location ~ ^/(deploy|test_.*\.php) { deny all; return 404; }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    client_max_body_size 50M;
}
NGINX
}

echo -e "${YELLOW}Step 9: Setting permissions${NC}"
chown -R www-data:www-data $WEB_ROOT
find $WEB_ROOT -type f -exec chmod 644 {} \;
find $WEB_ROOT -type d -exec chmod 755 {} \;
chmod 600 $WEB_ROOT/config/local.env

echo -e "${YELLOW}Step 10: Restarting services${NC}"
systemctl restart php8.3-fpm
systemctl reload nginx

echo ""
echo -e "${GREEN}=== Deployment Complete ===${NC}"
echo ""
echo -e "Website URL: https://$DOMAIN"
echo -e "Database: $DB_NAME"
echo -e "DB User: $DB_USER"
echo -e "DB Password: $DB_PASS"
echo ""
echo -e "${YELLOW}IMPORTANT: Add your OpenAI API key to:${NC}"
echo -e "$WEB_ROOT/config/local.env"
echo ""
echo -e "Default login:"
echo -e "  Username: admin"
echo -e "  Password: admin123"
echo ""
echo -e "${RED}Please change the admin password immediately!${NC}"
