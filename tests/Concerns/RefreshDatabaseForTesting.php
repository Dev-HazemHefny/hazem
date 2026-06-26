<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshDatabaseForTesting
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        $options = [
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
        ];

        if (config('database.default') === 'pgsql') {
            $options['--database'] = 'pgsql_migrate';
            $options['--force'] = true;
        }

        $seeder = $this->seeder();

        if ($seeder) {
            $options['--seeder'] = $seeder;
        } elseif ($this->shouldSeed()) {
            $options['--seed'] = true;
        }

        return $options;
    }
}
