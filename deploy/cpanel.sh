#!/usr/bin/env bash
set -eu

app="$(cd "$(dirname "$0")/../hr-app" && pwd)"
web="$HOME/public_html/hr.serinstech.com"
cd "$app"

export PATH="$HOME/bin:$PATH:/opt/cpanel/composer/bin"
if ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
    echo 'The hosting account default PHP CLI must be PHP 8.3 or newer.' >&2
    exit 1
fi
if ! command -v composer >/dev/null; then
    echo 'Install Composer 2 in ~/bin/composer or ask the host to enable Composer for this account.' >&2
    exit 1
fi

if [[ ! -s .env ]] || ! grep -Eq '^APP_KEY=.{8,}' .env; then
    echo 'Configure the production hr-app/.env and its permanent APP_KEY before deploying.' >&2
    exit 1
fi

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan migrate --force
php artisan optimize

mkdir -p "$web"
rsync -a public/fonts public/images public/workspace.css public/favicon.ico public/robots.txt "$web/"
if [[ ! -e "$web/.htaccess" ]]; then
    cp public/.htaccess "$web/.htaccess"
fi
if [[ ! -e "$web/index.php" ]]; then
    php -r 'echo "<?php\nrequire ", var_export($argv[1], true), ";\n";' "$app/public/index.php" > "$web/index.php"
fi
