<?php

namespace App\Commands;

use App\Support\Credentials;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class InstallSkillCommand extends Command
{
    protected $signature = 'install-skill {--global : Install for all projects (~/.claude/skills) instead of the current one}';

    protected $description = 'Install the Wethod agent skill so AI assistants can drive the CLI';

    public function handle(): int
    {
        $source = base_path('skills/wethod');

        if (! is_dir($source)) {
            error('Bundled skill not found at '.$source);

            return self::FAILURE;
        }

        $base = $this->option('global')
            ? Credentials::homeDir().'/.claude/skills'
            : getcwd().'/.claude/skills';

        $destination = $base.'/wethod';

        if (is_dir($destination) && ! confirm(
            label: "A Wethod skill already exists at {$destination}. Overwrite it?",
            default: true,
        )) {
            info('Skipped.');

            return self::SUCCESS;
        }

        $this->copyDirectory($source, $destination);

        info('Wethod skill installed to '.$destination);
        note('Your AI assistant can now manage Wethod data through the CLI.');

        return self::SUCCESS;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        foreach (Finder::create()->files()->in($source) as $file) {
            $target = $destination.'/'.$file->getRelativePathname();

            $dir = dirname($target);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            copy($file->getPathname(), $target);
        }
    }
}
