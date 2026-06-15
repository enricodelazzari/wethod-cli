<?php

it('refuses to run from a source checkout', function () {
    // The test suite itself runs from the Composer checkout, so the command
    // must never try to delete the project's own entry script.
    $this->artisan('uninstall', ['--force' => true])
        ->expectsOutputToContain('source checkout')
        ->assertExitCode(1);
});
