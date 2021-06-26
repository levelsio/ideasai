<?

	require_once(__DIR__.'/../app/apiKeys.php');

	loadDbs(array('gpt3ideas'));

	$query=$gpt3votesDb->prepare("SELECT idea FROM gpt3ideas");
	$query->execute();
	$allIdeas=$query->fetchAll(PDO::FETCH_ASSOC);
	$allWords=array();

	foreach($allIdeas as $idea) {
		$words=explode(' ',$idea);
		foreach($words as $word) {
			$allWords[$word]++;
		}
	}

	print_r($allWords);

	function loadDbs($dbs) {
		try {
			foreach($dbs as $db) {
				global ${$db.'Db'};

				// <load cities db>
					${$db.'DbFile'}=__DIR__.'/../data/'.$db.'.db';
					if(!file_exists(${$db.'DbFile'})) {
						echo ${$db.'DbFile'};
						echo ' does not exist';
					}
					// if old undeleted journal file found, delete it because it locks the db for writing
					if(file_exists(${$db.'DbFile'}.'-journal') && filemtime(${$db.'DbFile'}.'-journal')<strtotime("-5 minutes")) {
						rename(${$db.'DbFile'}.'-journal',${$db.'DbFile'}.'-journal_'.date('Y-m-d-H-i-s'));
					}
					${$db.'Db'} = new PDO('sqlite:/'.${$db.'DbFile'}) or die("Cannot open the database");
					${$db.'Db'}->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
					${$db.'Db'}->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					// echo "\n\n";
					// echo $db.'Db';
					// echo "\n\n";
					// print_r(${$db.'Db'});
					// echo "\n\n";
				// </load cities db>
			}
		}
		catch ( PDOException $e ) {
			echo 'ERROR!';
			print_r( $e );
		}
	}

?>