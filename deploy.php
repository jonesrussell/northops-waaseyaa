<?php

namespace Deployer;

require 'recipe/common.php';

// Config

set('keep_releases', 5);
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);
set('writable_dirs', ['storage']);

// Hosts

host('production')
    ->set('hostname', 'northops.ca')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '~/northops-waaseyaa');

// Tasks

task('deploy:upload', function (): void {
    upload('.build/', '{{release_path}}/');
});

task('deploy:clear_manifest', function (): void {
    run('rm -f {{deploy_path}}/shared/storage/framework/packages.php || true');
});

task('deploy:fix_permissions', function (): void {
    run('chmod 666 {{deploy_path}}/shared/storage/waaseyaa.sqlite || true');
    run('chmod 775 {{deploy_path}}/shared/storage/ || true');
});

task('deploy:reload_php_fpm', function (): void {
    run('sudo systemctl reload php8.4-fpm || true');
});

task('deploy:migrate', function (): void {
    run('cd {{release_path}} && php bin/waaseyaa migrate 2>&1 || true');
});

task('deploy:health_check', function (): void {
    $url = 'https://northops.ca/health';
    $result = run("curl -sf -o /dev/null -w '%{http_code}' $url 2>/dev/null || echo 'failed'");
    if ($result !== '200') {
        warning("Health check returned: $result (non-fatal)");
    } else {
        info('Health check passed');
    }
});

// Deploy flow

task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:upload',
    'deploy:shared',
    'deploy:writable',
    'deploy:migrate',
    'deploy:symlink',
    'deploy:fix_permissions',
    'deploy:clear_manifest',
    'deploy:reload_php_fpm',
    'deploy:health_check',
    'deploy:unlock',
    'deploy:cleanup',
]);

after('deploy:failed', 'deploy:unlock');
