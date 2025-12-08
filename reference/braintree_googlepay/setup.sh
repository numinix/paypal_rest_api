#!/bin/bash
set -e

# Install PHP CLI and required extensions
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y php-cli php-xml php-mbstring phpunit

