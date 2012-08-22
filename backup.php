<?php
    include('class.stemmer.inc');

    $host="localhost";
    $user="root";
    $pass="";
    $db_news="db_regex";

    $db_news1= mysql_connect($host,$user,$pass)or die(mysql_error());
    mysql_select_db($db_news, $db_news1) or die("Database Error News");

    $mysqli = new mysqli('localhost', 'root', '', 'dictionary');
    if (mysqli_connect_error()) {
        die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
    }

    $mysqli_geo = new mysqli('localhost','root','','db_geodata');
    if(mysqli_connect_error()){
        die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
    }

    /*
     *end of connection;
     */

    function array_iunique($array) {
        return array_intersect_key($array,array_unique(
        array_map(strtolower,$array)));
    }

    function in_arrayi($needle, $haystack){
        foreach ($haystack as $value){
            if (strtolower($value) == strtolower($needle))
            return true;
        }
        return false;
    }
    
    function count_words($str){
        $no = count(explode(" ",$str));
        return $no;
    }
    
    function array_trim($ar){
        foreach($ar as $key)
        if(empty($ar[$key]))
          unset($ar[$key]);
        
        return $ar;
    } 

    $exceptionalWord = array(
        "kompas",
        "standby",
        "peterpan",
        "ariel",
        "bandung",
        "yogyakarta",
        "kebonwaru", 
        "gp",
        "prix",
        "ferrari",
    );
    
    $placePrep = array(
        "at","on"
    );
    
    $sql_news="SELECT * FROM db_regex.tb_news";

    $view_news=mysql_query($sql_news,$db_news1);
    $list_new=mysql_fetch_array($view_news);
    $p_news = (string)$list_new['news'];

    $sentences = array();
    preg_match_all("~.*?[?.!,]~s",$p_news,$sentences);
    
    $places = array();
    $countries = array();
	
    foreach($sentences[0] as $sentence){
        
        $sentence = trim(preg_replace("/([().'?!;0-9&$]|\W|\s+)/i", " ", $sentence));
        $keywords = preg_split("/[\s]/i", $sentence);

        $words = array_values(array_trim($keywords));
        
        $stemmer = new Stemmer;
        
        $prevWord = 'start1';

        $skip = false;
        
        foreach($words as $index=>$word){
            
            //country name filter
            $geoQuery = "SELECT country_name FROM country WHERE country_name = '$word'";
            if($geoResult = $mysqli_geo->query($geoQuery)){
                if($geoResult->num_rows > 0){
                    $countries[]=$word;	
                        continue;
                }
            }
            
            //prepotition of places filter
            if($skip){
                $preposition = $words[$index-2];
            }else{
                $preposition = $words[$index-1];
            }

            if(in_arrayi($preposition, $placePrep)){
                if($word == 'the'){
                    $skip = true;
                }else if($skip){
                    $places[]= $word;
                    $skip = false;
                }
                else{
                    $places[] = $word;
                }
                continue;
            }
            
            //exceptional words
            if (in_arrayi($word, $exceptionalWord)){
                continue;
            }
            
            //negative word filter
            $negativeWord = preg_replace('/n$/','', $word);
            $negativeQuery = "SELECT word FROM words WHERE word = '$negativeWord'";
            if($negativeResult = $mysqli->query($negativeQuery)){
                if($negativeResult->num_rows > 0){
                    continue;
                }
            }
            
            //dictionary filter
            $dicQuery = "SELECT word FROM words WHERE word = '$word'";
            if ($dicResult = $mysqli->query($dicQuery)){
                if ($dicResult->num_rows > 0){
                    continue;
                }
            }
            
            //suffix filter
            $count_char = strlen($word);
            for ($x=1; $x<=$count_char; $x++){
                $suffixWord = '-'.substr($word, $count_char-$x, $x);
                $fixQuery = "SELECT word FROM words WHERE word = '$suffixWord'";
                if ($fixResult = $mysqli->query($fixQuery)){
                    if ($fixResult->num_rows > 0) {	
                        continue 2;
                    }
                }
            }
            
            //stemming filter
            $stemWord = $stemmer->stem($word);
            $stemQuery = "SELECT word FROM words WHERE word = '$stemWord'";
            if ($stemResult = $mysqli->query($stemQuery)){
                if ($stemResult->num_rows > 0){
                    continue;
                }
            }
            
            //null value filter
            if(empty($word)){
                continue;
            }	
            //get Name
            if($prevWord == 'start1'){
                $prevWord = 'start';
            }else{
                $prevWord = $words[$index-1];
            }
            
            $nextWord = $words[$index+1];
            if(empty($nextWord)){
                $nextWord = 'end';
            }
            
            $n_query = "SELECT word FROM words WHERE word = '$nextWord'";
            $p_query = "SELECT word FROM words WHERE word = '$prevWord'";

            if($n_result = $mysqli->query($n_query)){
                if($p_result = $mysqli->query($p_query)){
                    $prevRow = $p_result->num_rows;
                    $nextRow = $n_result->num_rows;

                    if(($prevRow > 0) && ($nextRow > 0)){
                        $allName[] = $word;
                    }else if(($prevRow > 0) && ($nextRow === 0)){
                        $var = $word;
                    }else if(($prevRow === 0) && ($nextRow === 0)){
                        $var = $var." ".$word;
                    }else if(($prevRow === 0) && ($nextRow > 0)){
                        $var = $var." ".$word;
                        $allName[] = $var;
                    }else {echo "else here";}
                    
                    continue;
                }
            }
            
        }//foreach, words
    }//foreach,kalimat.

    $uniqueNames = array_values(array_iunique($allName));
    $uniquePlaces = array_values(array_iunique($places));
    $uniqueCountries = array_values(array_iunique($countries));
	
    $orderNames = array();  
    
    foreach($uniqueNames as $names){
      $orderNames[$names] = count_words($names);
    }
    
    asort($orderNames);
    
    $nameKeys = array_keys($orderNames);
    $sumNames = count($nameKeys);
    $sumPlaces = count($uniquePlaces);
    
    for($i=0; $i<$sumNames; $i++){
        for($j=$i+1; $j < $sumNames; $j++){
            
            $com = $nameKeys[$i].' ';
            $firstName = strpos($nameKeys[$j], $com);
            if($firstName !== false){
                unset($nameKeys[$i]);
            }
            
            $com = ' '.$nameKeys[$i];
            $lastName = strpos($nameKeys[$j], $com);
            if($lastName !== false){
                unset($nameKeys[$i]);
            }
        }
    }
    
    for($i=0; $i<$sumPlaces; $i++){
        for($j=$i; $j < $sumNames; $j++){
            
            $comPlaces = $uniquePlaces[$i].' ';
            $firstName = strpos($nameKeys[$j], $comPlaces);
            if($firstName !== false){
                unset($uniquePlaces[$i]);
            }
            
            $comPlaces = ' '.$uniquePlaces[$i];
            $lastName = strpos($nameKeys[$j], $comPlaces);
            if($lastName !== false){
                unset($uniquePlaces[$i]);
            }
        }
    }
	
	//places dictionary filter
	/*foreach($uniquePlaces as $key=>$place){
		$dicQuery = "SELECT word FROM words WHERE word = '$place'";
            if ($dicResult = $mysqli->query($dicQuery)){
                if ($dicResult->num_rows > 0){
                    unset($uniquePlaces[$key]);
                }else{
                	continue;
                }
            }
			
	}*/
	
	$uniquePlaces = array_values($uniquePlaces);
	 
    echo '<b>Names:</b><br>';
    foreach(array_values($nameKeys) as $k => $value){
        echo $k.'='.$value.'<br>';
    }
          
    echo '<br><b>Places:</b><br>';
    foreach(array_values($uniquePlaces) as $k=>$place){
        echo $k.'='.$place.'<br>';
    }
	
	echo '<br><b>Countries:</b><br>';
    foreach(array_values($uniqueCountries) as $k=>$country){
        echo $k.'='.$country.'<br>';
    }
?>