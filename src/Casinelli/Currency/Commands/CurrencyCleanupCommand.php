<?php

namespace Casinelli\Currency\Commands;

use Cache;
use Illuminate\Console\Command;

class CurrencyCleanupCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'currency:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup currency cache';

    /**
     * Application instance.
     *
     * @var Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function fire()
    {
        Cache::forget('casinelli.currency');

        $this->info('Currency cache cleaned.');
    }
}
