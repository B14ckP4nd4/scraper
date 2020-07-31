<?php

namespace App\Console\Commands;

use App\targets;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class scraperInfomation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scraper:info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'show last Targeted you saved';

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

        // Get data

        $targets = targets::orderBy('id','desc')->where('scrapped',0);

        $count = $targets->count();


        // make table for OutPut
        $table = new Table($this->output);

        $rows = [];

        $seprator = new TableSeparator;

        $i=0;
        foreach($targets->get() as $target){

            $rows[] = [ $target->id , $target->url ];

            $rows[] = $seprator;

        }

        $this->info("[info] there is '".$count."' un-scrapped target here");

        $table->setHeaders([
            'ID', 'Target URL'
        ]);

        $table->setRows($rows);

        $table->render();

        $this->info("[info] there is '".$count."' un-scrapped target here");

        return 0;
    }
}
