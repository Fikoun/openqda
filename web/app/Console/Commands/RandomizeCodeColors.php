<?php

namespace App\Console\Commands;

use App\Models\Code;
use Illuminate\Console\Command;

class RandomizeCodeColors extends Command
{
    protected $signature = 'codes:randomize-colors';

    protected $description = 'Assign a unique random color to every code (reverses codes:reset-colors).';

    public function handle(): int
    {
        $count = Code::count();

        if ($count === 0) {
            $this->info('No codes found.');

            return self::SUCCESS;
        }

        if (! $this->confirm("This will assign a random color to each of the {$count} codes. Continue?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $updated = 0;
        Code::query()->lazyById()->each(function (Code $code) use (&$updated) {
            $code->color = $this->randomColor();
            $code->save();
            $updated++;
        });

        $this->info("Successfully randomized colors for {$updated} codes.");

        return self::SUCCESS;
    }

    private function randomColor(): string
    {
        $r = rand(160, 230);
        $g = rand(160, 230);
        $b = rand(160, 230);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
