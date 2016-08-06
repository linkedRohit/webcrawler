<?php
    //Inluding a library to do the DOM operations in php
    include('simple_html_dom.php');
    error_reporting(0); //This suppresses any notices, warnings and messages thrown by PHP.

    $crawler = new Crawler();
    $crawler->getInputsFromUser();
    $crawler->crawlTheSite($crawler->getUrl());

    //This is a crawler class that contains all the functions to perform crawling.
    class Crawler {
	protected $url = false;
	protected $numLinks = -1;

        //Indexed tree for fast and divided search.
	private $indexedBTree = array();

        public function getUrl() {
	    return $this->url;
	}

	public function getLinks() {
	    return $this->numLinks;
	}

 	public function getInputsFromUser() {
	    $this->url = $this->getUrlFromUser();
	    $this->numLinks = $this->getMaximumLinksToCrawl();
	}

	private function getUrlFromUser() {
	    $url = false;
	    echo "Please enter the Url you want to crawl " . "\n" . "(or it will run for https://www.python.org/ by default) : " . "\n\n URL : ";
	    fscanf(STDIN, "%sn", $url);
	    $url = $url ? $url : 'https://www.python.org/';
	    if(!$this->validateUrl($url)) {
		echo "You entered an Inalid Url, Bye for now" . "\n";exit(1);
	    }
	    return $url;
	}

        private function getMaximumLinksToCrawl() {
	    $links = 0;
	    echo "\n"."Please enter the number of links to crawl" . "\n\n" . "Link count : ";
	    fscanf(STDIN, "%sn", $links);
	    if(is_numeric($links) && $links > 0) {
		return $links;
	    } else {
		echo "You entered the invalid count, Bye for now" . "\n";exit(1);
	    }
	}

 	private function validateUrl($url) {
	    if (filter_var($url, FILTER_VALIDATE_URL) === false || strtolower(substr($url,0,4)) != "http") { //TO check if urls contain other protocols like mail, java, tel etc.
		return false;
	    }
	    return true;
	}

	//This will check whether the url is already inserted into the tree
	//If not, It will add the node to the tree.
	//The tree will be like as below: 
	//		www.python.org or user give url 	For other domains other than python.org or other user specified url  ....
	//	       /        |      \   \				/   |   \
	//      crawled URL1   URL2   URL3 ...			       /    |    \
	//      / / |  \  \
        // URL11 12 13 14 15 ...
	//This will make search faster as I can search the key with domain hash and then its children.
	private function pushToIndexedTree($url, $urlHash) {
	    $urlParts = parse_url($url);
	    $domain = isset($urlParts['host']) ? $urlParts['host'] : '';
	    if(!empty($domain)) {
		$domainHash = md5($domain);
		$domainUrlHash = md5($url);
	        $this->indexedBTree[$domainHash][$urlHash] = $url;
	    }
	}

	private function searchIndexedTree($url, $urlHash) {
	    $urlParts = parse_url($url);
            $domain = isset($urlParts['host']) ? $urlParts['host'] : '';
            if(!empty($domain)) {
                $domainHash = md5($domain);
                $foundNode = $this->indexedBTree[$domainHash][$urlHash];
            }
	    return isset($foundNode) ? $foundNode : false;
	}

	private function addUniqueUrlToIndexedTree($url) {
	    $hash = md5($url);
	    $addedAlready = $this->searchIndexedTree($url, $hash);
            if($addedAlready == false) {
		//Push to the indexed tree so that we can search faster
                $this->pushToIndexedTree($url, $hash);
		return true;
            }
	    return false;
        }

	//Getting crawledUrlsQueue parameter as reference.
	private function addCrawledUrls($urlsToAdd, &$crawledUrlsQueue) {
	    foreach($urlsToAdd as $url) {
		$addToQueue = $this->addUniqueUrlToIndexedTree($url);
		($addToQueue == true) ? array_push($crawledUrlsQueue, $url) : "";
	    }
	}

        public function crawlTheSite($url) {
	    //This will be in a messaging queue system like RabbitMQ or Kafka
	    //And then we can run multiple instances of the script in distributed architecture to crawl large number of urls.
	    //For now, I am keeping this as alike queue data structure in the php memory itself.
            $crawledUrlsQueue = array(); //This is a queue keeping crawled urls

	    //Push the initial url to the queue to implement Breadth First Search, so I can crawl the most important or immediate child links first.
	    //Also adding to the indexed tree.
	    $this->addCrawledUrls(array($url), $crawledUrlsQueue);

	    $crawledUrlsCount = 0; //Considering initial url out of the total count for crawled urls.
	    echo "Crawling the following urls : " . "\n";
	    while(count($crawledUrlsQueue) > 0 && $crawledUrlsCount <= $this->numLinks) {
		$crawlingUrl = $crawledUrlsQueue[0];
		array_splice($crawledUrlsQueue, 0, 1); // Getting the first element of the queue and pop the element out from queue FIFO.
		if(strtolower(substr($crawlingUrl,0,4)) != "http")
		    continue;
		echo $crawledUrlsCount . ".) " . $crawlingUrl . "\n";
		$urlsFound = $this->crawlUrl($crawlingUrl);
		$this->addCrawledUrls($urlsFound, $crawledUrlsQueue);
		$crawledUrlsCount++;
            }
	    echo "Finished crawling the url : " . $this->url . "\n";
	}

        private function crawlUrl($url) {
	    $baseUrl = parse_url($url)['scheme'].'://'.parse_url($url)['host'];
	    $urlsFound = $this->parseHtmlAndReturnUrls($url); 
	    for($i =0; $i < count($urlsFound); $i++) {
		$urlsFound[$i] = $this->rel2abs($urlsFound[$i], $baseUrl); //Getting absolute urls from relative urls found on page.
		if(!$this->validateUrl($urlsFound[$i])) {
		    unset($urlsFound[$i]);  
		}
	    }
	    if(count($urlsFound) > 0)
	        $urlsFound = array_values($urlsFound); //Reindexing the array values
	    else
		$urlsFound = array();
	    return $urlsFound; //*/array_splice($urlsFound, 0 , 5);*/
        }

	//Function picked up from internet search - Stack overflow.
	private function rel2abs($rel, $base) {
	    /* return if already absolute URL */
	    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;

	    /* queries and anchors */
	    if ($rel[0]=='#' || $rel[0]=='?') return $base.$rel;

	    /* parse base URL and convert to local variables:
	       $scheme, $host, $path */
	    extract(parse_url($base));

	    /* remove non-directory element from path */
	    $path ="";
	    $path = preg_replace('#/[^/]*$#', '', $path);

	    /* destroy path if relative url points to root */
	    if ($rel[0] == '/') $path = '';

	    /* dirty absolute URL */
	    $abs = "$host$path/$rel";

	    /* replace '//' or '/./' or '/foo/../' with '/' */
	    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
	    for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}

	    /* absolute URL is ready! */
	    return $scheme.'://'.$abs;
	}

	private function parseHtmlAndReturnUrls($url) {
	    $urlsFound = array();
	    try {
		$html = file_get_html($url);
		if($html ) {
	            // Find all links from anchor tags
	            foreach($html->find('a') as $element) 
	                array_push($urlsFound, $element->href);

    	            // Find all links from iframes on the page
                    foreach($html->find('iframe') as $element)
                        array_push($urlsFound, $element->src);

	            // Find all links from image tags
                    foreach($html->find('img') as $element)
                        array_push($urlsFound, $element->src);
		}
	    }
	    catch(Exception $ex) {
		//log the url for which we found exceptions
	 	return array();
	    }

	    return $urlsFound;
	/* Ignoring link and script tags, as we will not find any meaningful content from those urls
	   However If we need to do so, we can do as the below commented code and filter the files when reading content from them

	    // Find all links from anchor tags
            foreach($html->find('script') as $element)
                array_push($urlsFound, $element->src);
	    // Find all links from link tags
            foreach($html->find('link') as $element)
                array_push($urlsFound, $element->href);

	    $filters = array("php", "js", "css", "jpeg", "jpg", "png", "gif", "tiff"
	    ...
	    ...

	*/
        }
    }
    
?>
