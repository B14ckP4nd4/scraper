<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class scraper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scraper:add {target_file : path of the target urls separated by new line \'\n\'}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a Target FILE';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $targets = $this->argument('target_file');
        if(!file_exists($targets) && filesize($targets) == 0)
            $this->error('Target file didn\'t find or its empty');

        $this->info($this->argument($targets));
        return 0;
    }
}
