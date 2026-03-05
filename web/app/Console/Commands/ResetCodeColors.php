<?php

namespace App\Console\Commands;

use App\Models\Code;
use Illuminate\Console\Command;

class ResetCodeColors extends Command
{
    protected $signature = 'codes:reset-colors
        {--color=#ebebeb : The color to set on all codes (default: #ebebeb)}';

    protected $description = 'Reset the color of all existing codes to the default (#ebebeb).';

    public function handle(): int
    {
        $color = $this->option('color');
        $count = Code::count();

        if ($count === 0) {
            $this->info('No codes found.');

            return self::SUCCESS;
        }

        if (! $this->confirm("This will update the color of {$count} codes to \"{$color}\". Continue?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $updated = Code::query()->update(['color' => $color]);

        $this->info("Successfully updated {$updated} codes to color \"{$color}\".");

        return self::SUCCESS;
    }
}
