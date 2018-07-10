#!/usr/bin/php
<?php
require __DIR__ . '/vendor/autoload.php';


use GuzzleHttp\Client;
use Noodlehaus\Config;
use League\Csv\Reader;
use League\Csv\Writer;

$cmd = new Commando\Command();

///// need to clean up

$cmd->option('c')
	->aka('config')
	->describe('Config file to load')
	->require()
	->file();
$cmd->option('createtag')
	->describe('Create Tag')
	->argument();

	$cmd->option('announcement')
		->describe('Send announcement')
		->argument();
$cmd->option('createchannel')
	->describe('Create Channel')
	->argument();
$cmd->option('export')
	->describe('Export')
	->argument();
$cmd->option('start')
	->describe('Start Date')
	->argument();
$cmd->option('end')
	->describe('End Date')
	->argument();
$cmd->option('customer')
	->describe('Customer')
	->argument();
$cmd->option('exporttags')
	->describe('Export tags')
	->argument();
$cmd->option('csvtags')
	->describe('Create Tags from csv')
	->file();
$cmd->option('replacetags')
	->describe('Replace Tags from csv')
	->file();
$cmd->option('upload')
	->describe('Upload a file')
	->file();
	$cmd->option('update')
		->describe('Update a file')
		->file();

$cmd->option('fileid')
			->describe('ID of file to upate')
			->argument();

	$cmd->option('folder')
	->describe('Upload a folder')
	->file();

	$cmd->option('autotag')
	->describe('Autotag files with folder tags')
	->file();

if(isset($cmd['config'])){

	try{
		$conf = Config::load($cmd['config']);
	//print_r($conf);
	}
	catch (Exception $ex){
		print("Something went horribly wrong with your config file");
		print_r($ex);
	}
}
else
	{
		print("No config file defined - can't do anything.");
		exit;

}
$account = $conf->get('account');
$client_id = $conf->get('clientid');
$secret = $conf->get('secret');
$password = $conf->get('password');
$username = $conf->get('user');
$base_url = sprintf('https://%s.showpad.biz/api/v3/',$account );
$oauth2Client = new Client(['base_url' => $base_url]);
print ("Authenticating...\r\n");
$client = new Client([
    	'defaults' => [
    	'auth' => [
        	$client_id,
        	$secret
    		],
    	//'debug' => 'true',
    	'exceptions' => false
]]
);
$body['grant_type'] = "password";
$body['username'] = $username;
$body['password'] = $password;

try{
	$response = $client->post($base_url.'oauth2/token',
	[
	    'body' =>  $body,
	    'headers' => ['Accept' => 'application/json']
	]);
	$data = $response->json();
	$access_token = $data['access_token'];
	$refresh_token = $data['refresh_token'];
}
catch(RequestException $e){
	print_r($e->getResponse());
//	$response = $e->getResponse()->json();
}
$commands = $cmd->getFlagValues();

/// usage: ./sp-cli.php -c showpad.yaml --export events --start 2017-04-01 --end 2017-06-30 --customer showpad

//---------------------------------------------------------
if( isset($commands['export'])){
	$type = $cmd['export'];
	// create folder to store files in
	if(isset($cmd['customer'])){ if(!is_dir($cmd['customer'])){  mkdir($cmd['customer']); }}
	if($type == "events" || $type=="all"){
		print_r("--- Exporting events ---".PHP_EOL);
		$events = array();
		$scrollid = '';
		$start = $cmd['start'];
		$end = $cmd['end'];
		$page = 1;
		$limit = 1000;
		try{

			$handle = fopen($cmd['customer'].'/'.$start.'--'.$end.'.csv', 'w') or die('Cannot open file:  '.$cmd['customer'].'/'.$start.'--'.$end.'.csv');

		//	$events = [ 0=> ['eventId','startTime','endTime','userId','channelId','shareId','divisionId','assetId','contactId','type','page' ]];
			$response = $client->get( $base_url.'exports/events.csv?startedAt='.$start.'&endedAt='.$end.'&pageBased=true&limit='.$limit,
			[
			    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
			]);


			$data=$response->getBody()->getContents();
			fwrite($handle, $data);
			// count lines in result
		    $count = substr_count( $data, "\n" );
		    //print $count;
			print_r("Receiving events page ".$page.PHP_EOL);
			$page++;
			$scrollid= $response->getHeader('x-showpad-scroll-id');
			while($count > 0){
				$response = $client->get( $base_url.'exports/events.csv?startedAt='.$start.'&endedAt='.$end.'&pageBased=true&limit='.$limit,
				[
					//add scrollid
			    	'headers' => ['Authorization' => sprintf('Bearer %s', $access_token), 'x-showpad-scroll-id' => $scrollid],
			  	]);

				$data=$response->getBody()->getContents();
				$count = substr_count( $data, "\n" );
				// remove 1st line - contains column names
				$parts = explode("\n", $data, 2);
				$data = $parts[1];
				fwrite($handle, PHP_EOL.$data);
				print_r("Receiving events page ".$page.PHP_EOL);
				$page++;
				$scrollid= $response->getHeader('x-showpad-scroll-id');
			}
			fclose($handle);
		}
			catch(RequestException $e){
				print_r('ERROR:'.$e);
			}

		}
		if ($type == "datamodel" || $type =="all") {
			print_r("--- Exporting data model ---".PHP_EOL);
			$types = ['assets','asset-tags','channels','contacts','divisions','shares','tags','users','usergroups','user-usergroups','devices'];
			foreach ($types as $item) {
			try{
				print_r("Exporting ".$item.PHP_EOL);
				$handle = fopen($cmd['customer'].'/'.$item.".csv", 'w') or die('Cannot open file:  '.$cmd['customer'].'/'.$item.'.csv');
					$response = $client->get( $base_url.'exports/'.$item.'.csv',
					[
					    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
					]);
					$data=$response->getBody()->getContents();
					fwrite($handle, $data);
					fclose($handle);
				}
				catch(RequestException $e){
					$response = $e->getResponse()->json();
					print_r($response);
				}
			}
		}
}

//---------------------------------------------------------------------------------------
if( isset($commands['createtag'])){
		createTag($cmd['createtag']);
}


//---------------------------------------------------------------------------------------
if( isset($commands['createchannel'])){
		$newchannel = $cmd['createchannel'];
		try{
			$response = $client->post( $base_url.'channels.json',
			[
			    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
			    'body' => ['name'=> $newchannel]
			]);
			$data = $response->json();
			print("Channel '".$newchannel."' ".$data['meta']['message']."\r\n");
	}
	catch(RequestException $e){
		$response = $e->getResponse()->json();
		print_r($response);
	}
}
//---------------------------------------------------------------------------------------
if( isset($commands['announcement'])){
		$newchannel = $cmd['createchannel'];
		try{
			$response = $client->post( $base_url.'channels.json',
			[
			    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
			    'body' => ['name'=> $newchannel]
			]);
			$data = $response->json();
			print("Channel '".$newchannel."' ".$data['meta']['message']."\r\n");
	}
	catch(RequestException $e){
		$response = $e->getResponse()->json();
		print_r($response);
	}
}
//---------------------------------------------------------------------------------------
if( isset($commands['csvtags'])){
	$csvfile = $cmd['csvtags'];
	$reader = Reader::createFromPath($csvfile);
	foreach ($reader as $index => $row) {
			if(isset($row[0]) && strlen(trim($row[0])) >0)
			{
				try{
					$response = $client->post( $base_url.'tags.json',
					[
					    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
					    'body' => ['name'=> $row[0]]
					]);
					$data = $response->json();
					print("Tag '".$row[0]."' ".$data['meta']['message']."\r\n");
					//print_r($response->json());
				}
			catch(RequestException $e){
				$response = $e->getResponse()->json();
				print_r($response);
			}
		}
	}
}
//---------------------------------------------------------------------------------------
if( isset($commands['exporttags'])){
	$csvfile = $cmd['exporttags'];
$writer = Writer::createFromPath($csvfile, "c+");
$header = ["id" , "tag", "replacement"];
	$writer->insertOne($header);
		try{
				$response = $client->get( $base_url.'tags.json',
				[
				    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)]
				]);
				$data = $response->json();
				$tags = array();
				foreach ($data['response']['items'] as $key => $value) {
					$writer->insertOne([$value['id'],$value['name'],'' ]);
					echo "item ";
					$tags[$value['name']] = $value['id'];
				}
		}
		catch(RequestException $e){
			$response = $e->getResponse()->json();
			print_r($response);
		}
}

//---------------------------------------------------------------------------------------
if( isset($commands['replacetags'])){
	$csvfile = $cmd['replacetags'];
	$reader = Reader::createFromPath($csvfile);
	/// $header = ["id" , "tag", "replacement"];
//	$headers = $reader->fetchOne(0);
foreach ($reader as $index => $row) {
			if(isset($row[0]) && strlen(trim($row[0])) >0)
			{
				try{
						$id = $row[0];
						$orig = $row[1];
						$new = $row[2];
				if(strlen($new)>0 && $new != $orig && $id!="id"){
							$response = $client->post( $base_url.'tags/'.$id.'.json',
							[
							    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
							    'body' => ['name'=> $new, 'id' => $id],
							]);
							print("replacing ".$orig. " with ". $new. " for id ".$id."\r\n" );
							$data = $response->json();
							print("Tag '".$new."' ".$data['meta']['message']."\r\n");
					}
				}
			catch(RequestException $e){
				$response = $e->getResponse()->json();
				print_r($response);
			}
		}
	}
}
//---------------------------------------------------------------------------------------
if( isset($commands['upload'])){
		$file = $cmd['upload'];
		try{
			$response = $client->post( $base_url.'assets.json',
			[
			    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
			    'body' => ['file'=> fopen($file, 'r')]
			]);
			$data = $response->json();
			print("File '".$file."' ".$data['meta']['message']."\r\n");
			$id = $respone['respone']['id'];
			print_r($data);
	}
	catch(RequestException $e){
		$response = $e->getResponse()->json();
		print_r($response);
	}
}
//---------------------------------------------------------------------------------------
if( isset($commands['update'])){
		$file = $cmd['update'];
		$id = $cmd['fileid'];
		try{
			$response = $client->post( $base_url.'assets/'.$id.'.json',
			[
			    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
			    'body' => ['file'=> fopen($file, 'r')]
			]);
			$data = $response->json();
			print("File '".$file."' ".$data['meta']['message']."\r\n");
			$id = $respone['respone']['id'];
			print_r($data);
	}
	catch(RequestException $e){
		$response = $e->getResponse()->json();
		print_r($response);
	}
}
//---------------------------------------------------------------------------------------
if( isset($commands['folder'])){
		$folder = $cmd['folder'];


		$folderToCreate = array();
		$filesToUpload = array();
		$tagsToAdd = array();

		$prefix = realpath($folder);

		$ritit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder), RecursiveIteratorIterator::CHILD_FIRST);
		$r = array();

		print_r('Generating files, folders and tags lists...');
		print_r(PHP_EOL);

		foreach ($ritit as $splFileInfo) {
			$create = array();
   			if($splFileInfo->isDir() ){
   				$path = array($splFileInfo->getFilename() => array()) ;
   				if(strpos( $splFileInfo->getFilename(), '.') === false){
   					$folderToCreate[] = str_replace($prefix.'/', '', $splFileInfo->getPathname());
   			}
   			}
   			else{
   				$path = array($splFileInfo->getFilename());
   				if(strpos( $splFileInfo->getFilename(), '.') !== 0){

   					$tags = array_filter(explode('/', str_replace('/'.$splFileInfo->getFilename(),'',str_replace($prefix, '', $splFileInfo->getPathname())   )));


   					print_r( 'filename '.$splFileInfo->getFilename() );
   					print_r(PHP_EOL);
   						print_r( 'pathname '.$splFileInfo->getPathname() );
   						print_r(PHP_EOL);

   					print_r('trimmed: '. str_replace('/'.$splFileInfo->getFilename(),'',str_replace($prefix, '', $splFileInfo->getPathname())   ) );
   					print_r(PHP_EOL);

   					$create = array('file' =>  $splFileInfo->getFilename(),
   								'path' => $splFileInfo->getPathname(),
   								'tags' => $tags
   								);

   					foreach ($tags as $key => $value) {
	   					if (!in_array($value, $tagsToAdd))
						{
	    					$tagsToAdd[] = $value;
						}
   					}

   				$filesToUpload[] = $create;
   				}
   			}

   			}

   			print_r($filesToUpload);
			print_r(PHP_EOL);
   			print_r(count($filesToUpload). ' files to upload.');
			print_r(PHP_EOL);


       		print_r($folderToCreate);
       		print_r(PHP_EOL);

       		print_r($tagsToAdd);
       		print_r(PHP_EOL);


       		$existingTags = getTags();
       		print_r($existingTags);

       		foreach ($tagsToAdd as $tag) {
       			if(!array_key_exists(trim($tag), $existingTags)){
       				$id = createTag(trim($tag));
       				$existingTags[trim($tag)] = $id;
       			}
       		}

       	//----------------- Uploading files

       			foreach ($filesToUpload as $file) {

       				$data = uploadFile($file['path'], $file['tags'], $existingTags);
       				//print_r($data);
       				//print_r($file['file'].' uploaded with id '.$id);

       			}
}

function uploadFile($file, $tags, $existingTags){

	global $base_url;
	global $client;
	global $access_token;

	try{
			$linkHeader = "";
			foreach ($tags as $tag) {
				// don't check the existence of the tag - done above
				$linkHeader.='<'.$existingTags[$tag].'>;rel="Tag",';
			}
			$linkHeader = trim($linkHeader, ',');

			$response = $client->post( $base_url.'assets.json',
			[
			  //EXAMPLE:  'headers' => ['Authorization' => sprintf('Bearer %s', $access_token), 'Link' => '<90ba3fe04addc142774fc83f4d590dd1>;rel="Tag"'],
				'headers' => ['Authorization' => sprintf('Bearer %s', $access_token), 'Link' => $linkHeader],
			    'body' => ['file'=> fopen($file, 'r')]
			]);
			$data = $response->json();
			print("File '".$file."' ".$data['meta']['message']."\r\n");
			$id = $respone['respone']['id'];
			print_r(PHP_EOL);
			return $data;
	}
	catch(RequestException $e){
		$response = $e->getResponse()->json();
		print_r($response);
	}

}
function createTag($newtag){
	global $base_url;
	global $client;
	global $access_token;
	try{
			$response = $client->post( $base_url.'tags.json',
			[
			    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)],
			    'body' => ['name'=> $newtag]
			]);
			$data = $response->json();
			print("Tag '".$newtag."' ".$data['meta']['message']."\r\n");
			//print_r($response->json());
			//print_r($data['response']['id']);
			return $data['response']['id'];

	}
	catch(RequestException $e){
		$response = $e->getResponse()->json();
		print_r($response);
	}

}

function getTags(){
	global $base_url;
	global $client;
	global $access_token;
	$tags = array();
	try{
			$response = $client->get( $base_url.'tags.json',
				[
				    'headers' => ['Authorization' => sprintf('Bearer %s', $access_token)]
				]);
				$data = $response->json();
				$tags = array();
				foreach ($data['response']['items'] as $key => $value) {
					$tags[$value['name']] = $value['id'];

				}
		}
		catch(RequestException $e){
			$response = $e->getResponse()->json();
			print_r($response);
		}
		return $tags;


}



/*


///REFRESH TOKEN - could add token to config file and refresh when needed (optimization)
$body =  array();
$body['grant_type'] = "refresh_token";
$body['refresh_token'] = $refresh_token;
try{

		$response = $client->post( $base_url.'oauth2/token',
		[
		    'body' =>  $body,

		]);
		$data = $response->json();
	//	print_r($response->json());

		$access_token = $data['access_token'];
		$refresh_token = $data['refresh_token'];


}
catch(RequestException $e){
	$response = $e->getResponse()->json();
	print_r($response);
}

*/










?>
