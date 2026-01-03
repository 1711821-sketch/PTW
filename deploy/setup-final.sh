#!/bin/bash
# Final setup script - adds OpenAI API key and configures webhook

echo "=== Final PTW Setup ==="

# Add OpenAI API key
echo "Adding OpenAI API key..."
sudo sed -i "s/OPENAI_API_KEY=.*/OPENAI_API_KEY=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/" /var/www/ptw/config/local.env
echo "OpenAI API key added!"

# Generate webhook secret
WEBHOOK_SECRET=$(openssl rand -hex 32)
echo ""
echo "Setting up GitHub webhook..."
sudo sed -i "s/define('WEBHOOK_SECRET', '.*');/define('WEBHOOK_SECRET', '$WEBHOOK_SECRET');/" /var/www/ptw/deploy/webhook.php

# Enable webhook in nginx (remove deny rule for webhook)
sudo sed -i '/location ~ ^\/deploy/d' /etc/nginx/sites-available/ptw
sudo nginx -t && sudo systemctl reload nginx

echo ""
echo "=== Setup Complete ==="
echo ""
echo "OpenAI API key: Added to /var/www/ptw/config/local.env"
echo ""
echo "GitHub Webhook Setup:"
echo "  URL: https://ptw.interterminals.app/deploy/webhook.php"
echo "  Secret: $WEBHOOK_SECRET"
echo ""
echo "To setup webhook in GitHub:"
echo "1. Go to: https://github.com/1711821-sketch/PTW/settings/hooks"
echo "2. Click 'Add webhook'"
echo "3. Payload URL: https://ptw.interterminals.app/deploy/webhook.php"
echo "4. Content type: application/json"
echo "5. Secret: $WEBHOOK_SECRET"
echo "6. Events: Just the push event"
echo "7. Click 'Add webhook'"
