<?

	loadDbs(array('gpt3ideas'));

	$query=$gpt3ideasDb->prepare("SELECT idea FROM gpt3ideas");
	$query->execute();
	$allIdeas=$query->fetchAll(PDO::FETCH_ASSOC);
	$allWords=array();

	foreach($allIdeas as $idea) {
		$idea=str_replace('-',' ',makeUrlSlug($idea['idea']));
		echo $idea;
		echo "\n";
		$words=explode(' ',$idea);
		foreach($words as $word) {
			$allWords[$word]++;
		}
	}

	asort($allWords);
	array_reverse($allWords);

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

	function makeUrlSlug($str, $replace=array(), $delimiter='-') {
			// remove accents
			$str=trim($str);
			$str = removeAccents($str);
			
			if( !empty($replace) ) {
				$str = str_replace((array)$replace, ' ', $str);
			}

			@$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
			$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
			$clean = strtolower(trim($clean, '-'));
			$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

			if(substr($clean,0,1)=='-') {
				$clean=substr($clean,1,strlen($clean));
			}
			if(substr($clean,strlen($clean)-1,strlen($clean))=='-') {
				$clean=substr($clean,0,strlen($clean)-1);
			}

			return $clean;
		}
?>