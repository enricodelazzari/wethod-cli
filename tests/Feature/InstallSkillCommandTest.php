<?php

beforeEach(function () {
    $this->workdir = sys_get_temp_dir().'/wethod-skill-'.uniqid();
    mkdir($this->workdir, 0755, true);
    $this->cwd = getcwd();
    chdir($this->workdir);
});

afterEach(function () {
    chdir($this->cwd);

    exec('rm -rf '.escapeshellarg($this->workdir));
});

it('copies the bundled skill into the project .claude/skills directory', function () {
    $this->artisan('install-skill')->assertExitCode(0);

    $installed = $this->workdir.'/.claude/skills/wethod';

    expect(is_file($installed.'/SKILL.md'))->toBeTrue()
        ->and(is_file($installed.'/references/commands.md'))->toBeTrue()
        ->and(is_file($installed.'/references/workflows.md'))->toBeTrue();
});
