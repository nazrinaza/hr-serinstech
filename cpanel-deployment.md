# HR SeriNSTech on cPanel

The Laravel application lives in `hr-app`. The local starter uses SQLite. These first-deployment instructions assume a new MySQL database, a dedicated HTTPS subdomain and the hosting account’s default PHP 8.3.37.

1. In cPanel, inherit the account PHP version for the subdomain when it is PHP 8.3.37. Confirm the terminal uses the same version and has Composer 2 and PDO MySQL available.
2. Upload the application to a private directory such as `/home/ACCOUNT/apps/hr-app`. Configure the subdomain document root as `/home/ACCOUNT/apps/hr-app/public`. Include the supplied public/.htaccess. If your plan restricts document roots, have the host configure this mapping before deployment.
3. Create a dedicated database and database user in cPanel. Grant that user privileges on the application database.
4. In the application directory, copy `.env.example` to a new production `.env`. Set `APP_NAME="HR SeriNSTech"`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the HTTPS URL and `SESSION_SECURE_COOKIE=true`. Set `DB_CONNECTION=mysql` and fill in DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD with your host's values. Keep the production environment file private.
5. For a fresh deployment run the following from the application directory. Generate the application key once and keep it across deployments.

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   composer check-platform-reqs --no-dev
   php artisan key:generate
   php artisan migrate --force
   php artisan optimize
   ```

6. Give the PHP process write access to `storage` and `bootstrap/cache`. Visit the subdomain and its `/up` health route over HTTPS. For future views using Vite, build with Node locally or in CI and deploy the generated `public/build` directory.
