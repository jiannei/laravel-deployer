<?php

/*
 * This file is part of the Jiannei/laravel-deployer.
 *
 * (c) Jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Deployer;

/**
 * Get current git HEAD branch as default branch to deploy.
 */
set('branch', function () {
    try {
        $branch = runLocally('git rev-parse --abbrev-ref HEAD');
    } catch (\Throwable $exception) {
        $branch = null;
    }

    if ($branch === 'HEAD') {
        $branch = null; // Travis-CI fix
    }

    if (input()->hasOption('branch') && ! empty(input()->getOption('branch'))) {
        $branch = input()->getOption('branch');
    }

    return $branch;
});

/**
 * Whether to use git cache.
 *
 * Faster cloning by borrowing objects from existing clones.
 */
set('git_cache', function () {
    $gitVersion = run('{{bin/git}} version');
    $regs = [];
    if (preg_match('/((\d+\.?)+)/', $gitVersion, $regs)) {
        $version = $regs[1];
    } else {
        $version = '1.0.0';
    }

    return version_compare($version, '2.3', '>=');
});

desc('Update code');
task('deploy:update_code:v6', function () {
    $repository = trim(get('repository'));
    $branch = get('branch');
    $git = get('bin/git');
    $gitCache = get('git_cache');
    $recursive = get('git_recursive', true) ? '--recursive' : '';
    $dissociate = get('git_clone_dissociate', true) ? '--dissociate' : '';
    $quiet = '';
    $depth = $gitCache ? '' : '--depth 1';
    $options = [
        'tty' => get('git_tty', false),
    ];

    $at = '';
    if (! empty($branch)) {
        $at = "-b $branch";
    }

    // If option `tag` is set
    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
        if (! empty($tag)) {
            $at = "-b $tag";
        }
    }

    // If option `tag` is not set and option `revision` is set
    if (empty($tag) && input()->hasOption('revision')) {
        $revision = input()->getOption('revision');
        if (! empty($revision)) {
            $depth = '';
        }
    }

    // Enter deploy_path if present
    if (has('deploy_path')) {
        cd('{{deploy_path}}');
    }

    if ($gitCache && has('previous_release')) {
        try {
            run("$git clone $at $recursive $quiet --reference {{previous_release}} $dissociate $repository  {{release_path}} 2>&1", $options);
        } catch (\Throwable $exception) {
            // If {{deploy_path}}/releases/{$releases[1]} has a failed git clone, is empty, shallow etc, git would throw error and give up. So we're forcing it to act without reference in this situation
            run("$git clone $at $recursive $quiet $repository {{release_path}} 2>&1", $options);
        }
    } else {
        // if we're using git cache this would be identical to above code in catch - full clone. If not, it would create shallow clone.
        run("$git clone $at $depth $recursive $quiet $repository {{release_path}} 2>&1", $options);
    }

    if (! empty($revision)) {
        run("cd {{release_path}} && $git checkout $revision");
    }
});