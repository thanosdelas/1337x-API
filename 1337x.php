<?php 
if (php_sapi_name()!=='cli'){
	define("CLI", false);
}
else if (php_sapi_name()==='cli'){
	define("CLI", true);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
system('clear');
// include classes and functions
require_once("config.php");
require_once("includes/dbhandler.class.php");
require_once("includes/ansi.color.class.php"); $color = new Color();
require_once("includes/functions.php");

spl_autoload_register(function ($class_name) {
    require_once 'includes/'.$class_name.'.class.php';
});


//$url="https://1337x.to/category-search/A Good Day to Die Hard/Movies/1/";
//$url="https://1337x.to/category-search/A Good Day to Die Hard/Movies/1/";
//$url="https://1337x.to/category-search/A%20Good%20Day%20to%20Die%20Hard/Movies/1/";
//testCurl($url);

///////////////////////////////
// Parse command line arguments
if (CLI):
$allowedArgs=['search'=>false, 'html-stats'=>false, 'parse-torrent'=>false];
//if (!array_key_exists($argv[1], $allowedArgs)) commandLineHelp();
if ( count($argv)===2 ){
	switch($argv[1]):
		case "search":
			$allowedArgs['search']=true;
			break;
		case "html-stats":
			$allowedArgs['html-stats']=true;
			break;
		case "parse-torrent":
			$allowedArgs['parse-torrent']=true;
			break;
		default:
		commandLineHelp();	
	endswitch;
} else { commandLineHelp(); }
endif; //CLI
// Parse command line arguments
///////////////////////////////


/*
 *  Search results foreach movie from 1337x
 *  Save HTML results pages
 */
if (CLI):
if ($allowedArgs['search']):
	printColor (n."Searching ... ".n, "white+bold");
	
	$ImdbList = new ImdbList();
	if (CATEGORY=="Movies"):
		$titles = $ImdbList->getMoviesList();
		print ("Titles fecthed from database: ".count($titles).n);
	elseif (CATEGORY=="TV"):
		$titles = $ImdbList->getTvshowsList();
		print ("Titles fecthed from database: ".count($titles).n);
	endif;
	// Search1337x | Find how many results pages exist for each movie and save them to its own folder, named after moviename
	// Maybe reset the variables inside the class Search1337x rather than destroying it with unset(). Check for memory usage.
	// Main Loop | Might take a while according to results from ImdbList class | 

	foreach ( $titles as $title ){
		$search = new Search1337x();
		$search->searchForTitles($title);
		sleep(WAIT_SECONDS);
		unset($search);
	}
endif; //search
endif; //CLI
/*
 *	Collect and Parse search results from above
 */
if (CLI):
if ($allowedArgs['html-stats']):
	
	if (!file_exists(HTML_SEARCH_FILES_PATH)){
		printColor (n."[!]Directory does not exist: ".HTML_SEARCH_FILES_PATH,"white+bold");
		printColor (n."[!]HTML Results pages not found. Run search or transfer results files from another server.".n.n,"white");
		exit();
	}

	$folders=array_diff( scandir(HTML_SEARCH_FILES_PATH), array('.','..'));
	print ( n."Total Folders: ".count($folders).n.n);
	
	$counter=0;
	foreach ( $folders as $folder ):
	
		//if (++$counter > 1 ){break;}

		// Print stats
		$HTMLFiles=new ParseSearch1337x($folder);
		$HTMLFiles->collectSearchResultsHTML();
		$torrents = $HTMLFiles->collectTorrentInformation(); // Final array with torrent info for each folder
		if ( is_array($torrents) ){print (n."\t[*]Found: ".count($torrents)." torrents." );}

		$activeTorrents=0;
		foreach ($torrents as $t ):
		
			if ( $t['seeds']>MIN_SEEDS ){++$activeTorrents;}
			
		endforeach;
		print (n."\t[*]Active: ".$activeTorrents." ( seeds>".MIN_SEEDS." )");
		// Print stats
		
		// Download Torrent Pages HTML
		$countDownloaded=0;
		foreach ($torrents as $t ):

			if ( $t['seeds']>MIN_SEEDS ):
				$downloadHTMLTorrent= new DownloadHTMLTorrentPage( $folder, $t ); //$folder==imdb, $t==torrent Array
				$downloadHTMLTorrent->downloadTorrentHTMLPage();
				unset($downloadHTMLTorrent);
			endif;
			if (++$countDownloaded>10){break; printColor(n."Break after 10 torrent pages. Still testing.".n,"red");}

		endforeach; // Foreach torrent

	endforeach; // Foreach folder

endif; //html-stats
endif; //CLI

if (CLI):
//Test
if ($allowedArgs['parse-torrent']):
	
	printColor( n."Parse torrent Testing/Development".n,"green");
	$folder=HTML_TORRENTS_FILES_PATH."/tt0133093/";
	print ( n."Total HTML Files: ".count($files).n.n);
	foreach ($files as $f ){
		//print (n.$folder.$f);
		$parseTorrent = new ParseTorrentPage($folder.$f);
		$torrent = $parseTorrent->parseTorrentPage();
		print_r($torrent);
		//break;
	}
endif;
endif;//CLI

if (!CLI):

	$torrents=[];
	$folder=HTML_TORRENTS_FILES_PATH;
	if (!file_exists($folder)){ exit("No html files found.Please run search first."); }
	$folders=array_diff( scandir($folder), array('.','..'));

	foreach ($folders as $folderName ):

		$collectTorrents=[];
		$files=array_diff( scandir(HTML_TORRENTS_FILES_PATH."/".$folderName."/"), array('.','..'));
		foreach ($files as $f ):

			$parseTorrent = new ParseTorrentPage(HTML_TORRENTS_FILES_PATH."/".$folderName."/".$f);
			$torrent = $parseTorrent->parseTorrentPage();
			array_push($collectTorrents, $torrent);

		endforeach;
		
		$torrents[$folderName] = $collectTorrents;

	endforeach; // foreach folders

	if (HTML):
		include('styles.css');
		foreach ( $torrents as $imdb=>$ts ):

			print('<div class="torrent-header">');
				print("Imdb: ".$imdb);
				print_r(array_keys($ts[0]));
			print('</div>');
			
			foreach ($ts as $t){
				print("<h3>Title: ".$t['title']."</h3>");
				print('<div class="torrent-sub-header">');
					print('<pre>');
						print("<h4>Details</h4>");
						print_r($t['details']);
					print('</pre>');
					
					print('<pre>');					
						print("<h4>Links</h4>");
						//print_r($t['links']);
						foreach ($t['links']['downloadLinks'] as $link){
							print("<p>".$link."</p>");
						}
					print('</pre>');
					
					print('<pre>');
						print("<h4>Images</h4>");
						print_r($t['images']);
					print('</pre>');
					
				print('</div>');
			}

		endforeach;
	endif; //HTML
	
	if (JSON):
		print (json_encode($torrents));
	endif;

endif;
if (CLI)print ("\nDone\n");
?>