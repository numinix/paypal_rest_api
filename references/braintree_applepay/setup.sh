#!/usr/bin/env bash
# Setup script for Codex environment: installs PHP and PHPUnit
set -e

# Update package list
sudo apt-get update -y

# Install PHP and common extensions
sudo apt-get install -y php php-cli php-mbstring php-xml curl unzip

# Install PHPUnit using the official PHAR
curl -L https://phar.phpunit.de/phpunit.phar -o phpunit.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /usr/local/bin/phpunit

# Display versions to confirm installation
php -v
phpunit --version
