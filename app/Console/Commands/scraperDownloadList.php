<?php

namespace App\Console\Commands;

use App\downloadList;
use App\targets;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class scraperDownloadList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scraper:dllist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Count of undownloaded files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        set_time_limit(0);
        set_memory_limit('128M');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get data

        $targets = downloadList::orderBy('id','desc')->where('downloaded',0);

        $count = $targets->count();

        $this->info("[info] there is '".$count."' UnDownloaded File");

        return 0;
    }
}
