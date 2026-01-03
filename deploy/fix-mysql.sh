#!/bin/bash
# Fix PostgreSQL jsonb syntax to MySQL JSON syntax

echo "Fixing PostgreSQL jsonb syntax for MySQL..."

# Fix view_wo.php
sed -i "s/approvals::jsonb->>'opgaveansvarlig'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.opgaveansvarlig'))/g" /var/www/ptw/view_wo.php
sed -i "s/approvals::jsonb->>'drift'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.drift'))/g" /var/www/ptw/view_wo.php
sed -i "s/approvals::jsonb->>'entreprenor'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.entreprenor'))/g" /var/www/ptw/view_wo.php
echo "Fixed view_wo.php"

# Fix map_wo.php
sed -i "s/approvals::jsonb->>'opgaveansvarlig'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.opgaveansvarlig'))/g" /var/www/ptw/map_wo.php
sed -i "s/approvals::jsonb->>'drift'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.drift'))/g" /var/www/ptw/map_wo.php
sed -i "s/approvals::jsonb->>'entreprenor'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.entreprenor'))/g" /var/www/ptw/map_wo.php
echo "Fixed map_wo.php"

# Fix auth_check.php (if not already fixed)
sed -i "s/approvals::jsonb->>'opgaveansvarlig'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.opgaveansvarlig'))/g" /var/www/ptw/auth_check.php
sed -i "s/approvals::jsonb->>'drift'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.drift'))/g" /var/www/ptw/auth_check.php
sed -i "s/approvals::jsonb->>'entreprenor'/JSON_UNQUOTE(JSON_EXTRACT(approvals, '\$.entreprenor'))/g" /var/www/ptw/auth_check.php
echo "Fixed auth_check.php"

# Fix create_sja.php
sed -i "s/?::jsonb/CAST(? AS JSON)/g" /var/www/ptw/create_sja.php
echo "Fixed create_sja.php"

echo ""
echo "All files fixed! Try logging in now."
