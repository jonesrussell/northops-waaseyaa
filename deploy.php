<?php

namespace Deployer;

require 'recipe/common.php';

// Config

set('repository', 'git@github.com:jonesrussell/northops-waaseyaa.git');
set('keep_releases', 5);

set('shared_files', ['.env', 'waaseyaa.sqlite']);
set('shared_dirs', ['storage']);
set('writable_dirs', ['storage']);

// Hosts

host('northops.ca')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '~/northops');

// Tasks

task('deploy:vendors', function (): void {
    cd('{{release_path}}');
    run('composer install --no-dev --no-interaction --optimize-autoloader');
});

task('deploy:clear_manifest', function (): void {
    run('rm -f {{deploy_path}}/shared/storage/framework/packages.php || true');
});

task('deploy:reload_php_fpm', function (): void {
    run('sudo systemctl reload php8.4-fpm || true');
});

// Deploy flow

task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:vendors',
    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',
    'deploy:clear_manifest',
    'deploy:reload_php_fpm',
    'deploy:unlock',
    'deploy:cleanup',
]);

after('deploy:failed', 'deploy:unlock');
