#!/bin/bash
# Fix PostgreSQL INTERVAL syntax to MySQL syntax

echo "Fixing PostgreSQL INTERVAL syntax for MySQL..."

# Fix dashboard.php - INTERVAL '30 days' to INTERVAL 30 DAY
sudo sed -i "s/INTERVAL '30 days'/INTERVAL 30 DAY/g" /var/www/ptw/dashboard.php
sudo sed -i "s/INTERVAL '7 days'/INTERVAL 7 DAY/g" /var/www/ptw/dashboard.php
sudo sed -i "s/INTERVAL '1 day'/INTERVAL 1 DAY/g" /var/www/ptw/dashboard.php

# Fix time_overblik.php
sudo sed -i "s/INTERVAL '30 days'/INTERVAL 30 DAY/g" /var/www/ptw/time_overblik.php
sudo sed -i "s/INTERVAL '7 days'/INTERVAL 7 DAY/g" /var/www/ptw/time_overblik.php

# Fix any other files with INTERVAL syntax
find /var/www/ptw -name "*.php" -exec sudo sed -i "s/INTERVAL '30 days'/INTERVAL 30 DAY/g" {} \;
find /var/www/ptw -name "*.php" -exec sudo sed -i "s/INTERVAL '7 days'/INTERVAL 7 DAY/g" {} \;
find /var/www/ptw -name "*.php" -exec sudo sed -i "s/INTERVAL '1 day'/INTERVAL 1 DAY/g" {} \;
find /var/www/ptw -name "*.php" -exec sudo sed -i "s/INTERVAL '1 days'/INTERVAL 1 DAY/g" {} \;

# Fix CURRENT_DATE syntax if needed
find /var/www/ptw -name "*.php" -exec sudo sed -i "s/CURRENT_DATE - INTERVAL/CURDATE() - INTERVAL/g" {} \;

echo "All INTERVAL syntax fixed!"
