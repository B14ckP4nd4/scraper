<?php

namespace App\Console\Commands;

use App\downloadList;
use App\targets;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class scraperScanner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scraper:scan {--deep=true : deep scan will scan all directorie and Files in Target} {--queue=scrapper}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'scan the targets and download Files';

    protected $deepScan,
                $targets,
                $PROXY_HOST,
                $PROXY_PORT,
                $PROXY_USER,
                $PROXY_PASS,

                $directories = [],
                $files = [],

                $currentUrl,

                $tracedURLs = [],


                $extensions = [
                    'JPEG',
                    'JPG',
                    'PNG',
                    'GIF',
                    'TIFF',

                     'PDF',

                    'PSD',
                    'EPS',
                    'AI',
                    'INDD',

                    'RAW',
                    'CR2',
                    'CRW',
                    'NEF',
                    'PEF',

                    'DOC',
                    'DOCX',

                    'TXT',
                    'RTF',
                  ],

                $downloadList = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->PROXY_HOST = env('PROXY_HOST', '127.0.0.1');
        $this->PROXY_PORT = env('PROXY_PORT', 8086);
        $this->PROXY_USER = env('PROXY_USER', null);
        $this->PROXY_PASS = env('PROXY_USER', null);
        $this->PROXY_TYPE = env('PROXY_TYPE', 'HTTPS');

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Check is deep scan true or Not
        $this->deepScan = ( $this->option('deep') == 'true' ) ? true : false;

        // Get Unscrapped Targets From DB

        $this->targets = targets::where('scrapped',0)->get();

        foreach($this->targets as $target)
        {
            $this->info("[info] Start deep scan ".$target->url."\n");

            // LOG
            Log::info("Start Working On {$target->url}");

            // reset Download list
            $this->downloadList = [];

            // check if DeepScan is True
            if($this->deepScan)
            {
                // Search For Parent Directory
                $parent = $this->getParentDir($target->url);

                // LOG
                Log::info("Find this parent for deep scan : {$parent}");

                $this->info("[info] Parrent directori found $parent");

                // set Current URL
                $this->currentUrl = $parent;

                // Get Content of Parent Directory
                //$parentContent = $this->Request($parent);
                $this->info("[info] Get for links");
                //$links = $this->getLinks($parentContent);



                // LOG
                Log::info("Start Tracer : {$parent}");
                // Trace Directories
                $dirs = $this->traceDirectories($parent);

                // Save Download List
                $downloadList = $this->downloadList;
                $downloadList = new Collection($downloadList);
                $unique = $downloadList->unique()->all();

                foreach($unique as $downloadItem)
                {
                    $download = downloadList::updateOrCreate(
                        ['url' => $downloadItem]
                    );
                }
                $target->scrapped = true;
                $target->save();
            }
            else
            {

                // LOG
                Log::info("Start File Tracer : {$target}");

                $tracer = $this->traceFiles($target);

                // Save Download List
                $downloadList = $this->downloadList;
                $downloadList = new Collection($downloadList);
                $unique = $downloadList->unique()->all();

                foreach($unique as $downloadItem)
                {
                    $download = downloadList::updateOrCreate(
                        ['url' => $downloadItem]
                    );
                }
                $target->scrapped = true;
                $target->save();
            }
        }
    }

    // Trace All Directories in URL
    protected function traceDirectories(string $target){

        // Get Content of link
        $linkContent = $this->Request($target);

        if(!$this->isIndexOf($linkContent))
        {
            // LOG
            Log::warning("it's not an IndexOf page : {$target}");
            return false;
        }


        $links = $this->getLinks($linkContent);

        if(!$links)
        {
            // LOG
            Log::warning("couldn't find any link here : {$target}");
            return false;
        }


        //$this->tracedURLs[] = $target;

        foreach($links as $link)
        {
            // prepare URL
            $url = $this->generateUrl($target,$link);

            // ignore parent directories
            if(strlen($url) < strlen($target))
                continue;

            // save file For Download ignore Files
            if(!$this->isDir($url))
            {
                if($this->isDownloadable($url))
                    $this->downloadList[] = $url;

                continue;
            }

            // check subdirectories if is Dir
            if($this->isDir($url) && !in_array($url, $this->tracedURLs))
            {
                $this->directories[] = $url;

                // Get Content of SubDirectory
                //$subDirectoriContent = $this->Request($url);
                //$subDirectoriLinks = $this->getLinks($subDirectoriContent);

                // Trace Directories in Content
                $TraceDirectories = $this->traceDirectories($url);
                $this->tracedURLs[] = $url;
            }
        }
    }

    // trace File
    protected function traceFiles($target){
        // Get Content of link
        $linkContent = $this->Request($target);

//        if(!$this->isIndexOf($linkContent))
//        {
//            // LOG
//            Log::warning("it's not an IndexOf page : {$target}");
//            return false;
//        }

        $links = $this->getLinks($linkContent);

        if(!$links)
        {
            // LOG
            Log::warning("couldn't find any link here : {$target}");
            return false;
        }


        foreach($links as $link)
        {
            // prepare URL
            $url = $this->generateUrl($target,$link);

            // ignore parent directories
            if(strlen($url) < strlen($target))
                continue;

            // save file For Download ignore Files
            if(!$this->isDir($url))
            {
                if($this->isDownloadable($url))
                    $this->downloadList[] = $url;

                continue;
            }
        }
    }

    // check File is Downloadable
    protected function isDownloadable($fileUrl){
        foreach($this->extensions as $extension){
            if(stripos($fileUrl, $extension) > 0)
                return true;
        }

        return false;
    }

    // generate URL
    protected function generateUrl($currentUrl,$url){
        // Set Domain env
        $preg = preg_match("/(?<main>(https?:\/\/)?(www\.)?(?<domain>[^\/]+))(\/?(?<sub>.*))?/i", $currentUrl, $domain);
        $domain = $domain['main'];


        if (filter_var($url, FILTER_VALIDATE_URL))
            return $url;


        // Check URL without '/' at First
        if(substr($url,0,1) == '/')
            return rtrim($domain,'/') . '/' . ltrim($url,'/');

        // return format like http://domaon.com/url
        return rtrim($currentUrl,'/') . '/' . ltrim($url,'/');
    }

    // Go to first directory of Index of
    protected function getParentDir($url)
    {

        $url = rtrim($url, '\/');
        $preg = preg_match("/(?<main>(https?:\/\/)?(www\.)?(?<domain>[^\/]+))(\/?(?<sub>.*))?/i", $url, $match);

        if (!$match['main'])
            return false;

        // Return main Url if 'index of' is on Home page
        if (!$match['sub'])
            return $url;

        $subFolders = trim($match['sub'], '\/');
        $subFolders = explode('/', $subFolders);

        // search for main 'Index of' path

        $target = $match['main'];

        for ($i = 0; $i < count($subFolders); $i++) {
            $target .= '/' . $subFolders[$i];
            $content = $this->Request($target);

            // check is this Index of Page or not
            if ($this->isIndexOf($content))
                return $target;
        }

        // Return the URL if couldn't find any Parent page
        return $url;
    }

    // is it a 'Index of' Page
    protected function isIndexOf($content): bool
    {
        if (stripos($content, 'index of'))
            return true;

        return false;
    }

    // Search For Links
    protected function getLinks($content)
    {
        $preg = preg_match_all('/<a\s+(?:[^"\'>]+|"[^"]*"|\'[^\']*\')*href=("|\')(?<link>[^\']|[^"]|[^<>\s]+)("|\')/i', $content, $match);

        if (!empty($match['link']))
            return new Collection($match['link']);

        return false;
    }

    // check link is directory
    protected function isDir($url){
        if(!$this->isFile($url))
            return true;

        return false;
    }

    // check link is file
    protected function isFile($url)
    {
        $preg = preg_match('@^.*\.[\w\d]+$@i',$url,$match);
        if(isset($match[0]))
            return true;

        return false;
    }

    // Handle Curl Request for Scanner
    protected function Request($url){
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

        if($this->PROXY_TYPE == 'socks5')
        {
            //Set the proxy IP.
            curl_setopt($ch, CURLOPT_PROXY, "$this->PROXY_HOST:$this->PROXY_PORT");

            //Set the port.
            //curl_setopt($ch, CURLOPT_PROXYPORT, $this->PROXY_PORT);

            // Set Curl Type SOCKS5 FOR Using on TOR
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);

            if($this->PROXY_USER && $this->PROXY_PASS)
            {
                //Specify the username and password.
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$this->PROXY_USER:$this->PROXY_PASS");
            }

        }
        else
        {

            //Set the proxy IP.
            curl_setopt($ch, CURLOPT_PROXY, "$this->PROXY_HOST");

            //Set the port.
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->PROXY_PORT);

            // Set Curl Type SOCKS5 FOR Using on TOR
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);

            if($this->PROXY_USER && $this->PROXY_PASS)
            {
                //Specify the username and password.
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$this->PROXY_USER:$this->PROXY_PASS");
            }
        }

        //Set the proxy IP.
        curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:9050');

        //Set the port.
        curl_setopt($ch, CURLOPT_PROXYPORT, $this->PROXY_PORT);

        // Set Curl Type SOCKS5 FOR Using on TOR
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);

        //Specify the username and password.
        //curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$this->PROXY_USER:$this->PROXY_PASS");

        return curl_exec($ch);
    }
}
