<?php

namespace App\Console\Commands;

use App\targets;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class scraper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scraper:add {target_file : path of the target urls separated by new line \'\n\'} {save_path? : path to save images}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a Target FILE';


    protected $PROXY_HOST,
        $PROXY_PORT,
        $PROXY_USER,
        $PROXY_PASS,

        $SavePath,

        $TargetsFile,
        $targetURL,
        $targetHeaders,
        $targetContent;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->PROXY_HOST = '127.0.0.1';
        $this->PROXY_PORT = 8086;
        $this->PROXY_USER = 'test';
        $this->PROXY_PASS = 'cwncwn';

        $this->TargetsFile = null;
        $this->targetURL = '';
        $this->targetHeaders = '';
        $this->targetContent = '';
        $this->targetContent = '';

        $this->setProxy();

    }

    /**
     * Execute the console command.
     *
     * @return int
     */

    // Set Proxy for Headers requests
    private function setProxy()
    {

        $auth = base64_encode("$this->PROXY_USER:$this->PROXY_PASS");
        stream_context_set_default(
            [
                'http' => [
                    'proxy' => "tcp://$this->PROXY_HOST:$this->PROXY_PORT",
                    'request_fulluri' => true,
                    //'header' => "Proxy-Authorization: Basic $auth"
                    // Remove the 'header' option if proxy authentication is not required
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],

            ]
        );
    }


    public function handle()
    {
        $this->targetsFile = $this->argument('target_file');

        if (!$this->validateTargetFile($this->targetsFile)) {
            return $this->error('There is a problem with target file');
        }

        $targets = file_get_contents($this->targetsFile);
        $targets = str_replace("\r", '', $targets);
        $targets = explode("\n", $targets);
        $targets = array_unique($targets);
        $targets = new Collection($targets);
        $this->info("[info] Start checking targets...");

        foreach ($targets->filter()->all() as $target) {
            // Set Target
            $this->targetURL = $target;

            $this->info("[info] Start Checking : $this->targetURL");

            //$this->headers = get_headers($this->targetURL);


            //  parse url for get information
            if (!$this->validateTargetURL($this->targetURL)) {
                $this->error("[Failed] Target have some issue \n");
                continue;
            }

            // Save Target in db
            $saveTarget = targets::updateOrCreate(
                ['url' => $this->targetURL]
            );

            $this->info("[Done] saved \n-------------------------------\n");


            continue;


            // Set Content
            $this->info('[info] Get target content');
            $this->targetContent = $this->getTargetContent($this->targetURL);

            // check is this Index of Page or not
            if ($this->isIndexOf($this->targetContent)) {
                $this->mainDirectory = $this->getParentDir($this->targetURL);
            }

            $this->info('[info] parent path has been found : ' . $this->mainDirectory . '');

            // get Main Directory Content
            $mainContent = $this->getTargetContent($this->mainDirectory);

            // Scan Content fo Links
            $links = $this->scanForLinks($mainContent);



            return 0;
            $this->info($target);
        }

        return 0;
    }


    private function validateTargetFile($targetPath)
    {
        if (!file_exists($targetPath))
            return false;

        if (filesize($targetPath) <= 0)
            return false;


        return true;
    }

    // Validate Target URL before get content of
    private function validateTargetURL(string $url)
    {

        // validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL))
            return false;

        //
//        $this->info("[info] start validation of target");


        // check URL is Active or Not
//        if (!$this->headers) {
//            $this->error("[error] Target Headers does not set...");
//            return false;
//        }

        // check URL is Active or Not
//        if (!strpos($this->headers[0], '200 OK')) {
//            $this->line(dump($this->headers[0]));
//            $this->error("[error] Target is offline...");
//            return false;
//        }

        // Check content Type is text/html;
//        if (!strpos($this->headers[3], 'text/html;')) {
//            $this->error("[error] Target isn't a HTML Page...");
//            return false;
//        }

        // Set Domain env
        $preg = preg_match("/(?<main>(https?:\/\/)?(www\.)?(?<domain>[^\/]+))(\/?(?<sub>.*))?/i", $url, $match);

        $this->domain = $match['main'];

        return true;

    }

    // get content Using emulate user browser with curl
    private function getTargetContent($url)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0');
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ]);

        //Set the proxy IP.
        curl_setopt($ch, CURLOPT_PROXY, $this->PROXY_HOST);

        //Set the port.
        curl_setopt($ch, CURLOPT_PROXYPORT, $this->PROXY_PORT);

        //Specify the username and password.
        //curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$this->PROXY_USER:$this->PROXY_PASS");

        return curl_exec($ch);
    }

    // Scan for links
    private function scanForLinks($content)
    {
        $preg = preg_match_all('/<a\s+(?:[^"\'>]+|"[^"]*"|\'[^\']*\')*href=("|\')(?<link>[^\']|[^"]|[^<>\s]+)("|\')/i', $content, $match);

        if (!empty($match['link']))
            return new Collection($match['link']);

        return false;

    }

    // is it a 'Index of' Page
    private function isIndexOf($content): bool
    {
        if (stripos($content, 'index of'))
            return true;

        return false;
    }


}
