<?php
/*
    Script  : EXTRACTION ET ALIMENTATION DE LA BASE DE DONNEES r_energy EN UTILISANT PHP/CURL
    Author  : Adnan AHOUZI
    version : 1.0

    */

    /*
    --------------------------------------------------------------------
    Usage: Gratuit pour un usage personnel
    --------------------------------------------------------------------

    Requirements: PHP/CURL, Apache and MySQL 

    */
?>


<?php
    // Defining the basic scraping function
    function scrape_between($data, $start, $end){
        $data = stristr($data, $start); // Stripping all data from before $start
        $data = substr($data, strlen($start));  // Stripping $start
        $stop = stripos($data, $end);   // Getting the position of the $end of the data to scrape
        $data = substr($data, 0, $stop);    // Stripping all data from after and including the $end of the data to scrape
        return $data;   // Returning the scraped data from the function
    }
?>

<?php   
    // Defining the basic cURL function
    function curl($url) {
        // Assigning cURL options to an array
        $options = Array(
            CURLOPT_RETURNTRANSFER => TRUE,  // Setting cURL's option to return the webpage data
            CURLOPT_FOLLOWLOCATION => TRUE,  // Setting cURL to follow 'location' HTTP headers
            CURLOPT_AUTOREFERER => TRUE, // Automatically set the referer where following 'location' HTTP headers
            CURLOPT_CONNECTTIMEOUT => 120,   // Setting the amount of time (in seconds) before the request times out
            CURLOPT_TIMEOUT => 120,  // Setting the maximum amount of time for cURL to execute queries
            CURLOPT_MAXREDIRS => 10, // Setting the maximum number of redirections to follow
            CURLOPT_USERAGENT => "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1a2pre) Gecko/2008073000 Shredder/3.0a2pre ThunderBrowse/3.2.1.8",  // Setting the useragent
            CURLOPT_URL => $url, // Setting cURL's URL option with the $url variable passed into the function
			CURLOPT_COOKIESESSION => 1, //Pour minimiser l'apparition de CAPTCHA
        );
         
        $ch = curl_init();  // Initialising cURL
        curl_setopt_array($ch, $options);   // Setting cURL's options using the previously assigned array data in $options
        $data = curl_exec($ch); // Executing the cURL request and assigning the returned data to the $data variable
        curl_close($ch);    // Closing cURL
        return $data;   // Returning the data from the function
    }
?>

<?php
include("LIB_http.php");
include("LIB_parse.php");


		try
		{
			$bdd = new PDO('mysql:host=localhost;dbname=r_energy', 'root', '');
		}
		catch(Exception $e)
		{
				die('Erreur : '.$e->getMessage());
		}

			

	   /*Ouvre le fichier et retourne un tableau contenant une ligne par élément*/
		$lines = file('urls8.txt');
		/*On parcourt le tableau $lines et on affiche le contenu de chaque ligne précédée de son numéro*/
		foreach ($lines as $lineNumber => $target)
		{
		echo $lineNumber.'<br/>';	 
		$web_page = http_get($target, "");
		
		 $table_array = parse_array($web_page['FILE'], "<td", "</td>");
		 for($xx=0; $xx<count($table_array); $xx++)
			{    
			if(stristr($table_array[$xx], "detailPCTtablePubDate"))        
				{
			$a= strip_tags(trim($table_array[$xx]));
			
			$a = str_replace('.', '-', $a);
			$a=trim($a);
			$a = date('Y-m-d', strtotime($a));
			$data_array['dateP'] = $a;
				}
				
			if(stristr($table_array[$xx], "detailPCTtableWO"))        
				{
			$b= strip_tags(trim($table_array[$xx]));
			$data_array['idArticle'] = $b;
				}
			}
			
		$test1=1;
		$test = 1;
			$table_array2 = parse_array($web_page['FILE'], "<tr", "</tr>");	
			for($xx=0; $xx<count($table_array2) AND $test1==1 ; $xx++)
			{
			if(stristr($table_array2[$xx], "<b>Title"))        
			{
			 $test1=0;
			$titre = strip_tags(trim($table_array2[$xx]));
			$titre = preg_split("#\(EN\)#", $titre);
			if(preg_match("#\(ES\)#", $titre[1]))
			{
			$titre =preg_split("#\(ES\)#", $titre[1]);
				$data_array['titre']=$titre[0];
			}
			else{
			$titre =preg_split("#\(FR\)#", $titre[1]);
			$data_array['titre']=$titre[0];
			}
			}
			}
			/*==============================================================*/
		/* INSERTION DANS LA TABLE ARTICLE                                     */
		/*==============================================================*/
		 // 
		 $req = $bdd->prepare('INSERT INTO article(idArticle, titre, dateP) VALUES(:nom, :possesseur, :date)');
			
		 $req->execute(array(
			'nom' => $data_array['idArticle'],
			'possesseur' => $data_array['titre'],
			'date' => $data_array['dateP']
			));
			
			//insert(DATABASE, $table="article", $data_array);
			
			for($xx=0; $xx<count($table_array2) AND $test==1 ; $xx++)
			{			
			if(stristr($table_array2[$xx], "Inventors:"))        
			{
			$test = 0;
			$table_cell_array = parse_array($table_array2[$xx], "<td", "</td>");
			$c = strip_tags(trim($table_cell_array[1]));
			
			
				$result_array = preg_split("#\)\.#", $c);
			for($j=0; $j<count($result_array); $j++)
			  {
			$result_array1 = preg_split("#\;#", $result_array[$j]);
			$result_array1[1] = preg_replace("#[()\n\t ]#", "", $result_array1[1]);
			
			
			/*==============================================================*/
		/* INSERTION DANS LA TABLE AUTEUR                                    */
		/*==============================================================*/
		 $req = $bdd->prepare('SELECT id FROM countrycodes WHERE code = :possesseur');
		 $req->execute(array('possesseur' => $result_array1[1]));
		$donnees = $req->fetch();
			$req = $bdd->prepare('INSERT INTO auteur(idPays, nomAuteur) VALUES(:nom, :possesseur)');
			
		 $req->execute(array(
			'nom' => $donnees['id'],
			'possesseur' => $result_array1[0]
			));
			
			
			
			/*==============================================================*/
		/* INSERTION DANS LA TABLE AVOIRAUTEUR                                     */
		/*==============================================================*/
			
			$req = $bdd->prepare('SELECT idAuteur FROM auteur WHERE nomAuteur = :possesseur');
		 $req->execute(array('possesseur' => $result_array1[0]));

		$donnees = $req->fetch();
		//echo $donnees['idAuteur'];
			
			$req = $bdd->prepare('INSERT INTO avoirauteur(idArticle, idAuteur) VALUES(:nom, :possesseur)');
			
		 $req->execute(array(
			'nom' => $data_array['idArticle'],
			'possesseur' => $donnees['idAuteur']
			));
			
			
			
			}
			

			
			}

			if(stristr($table_array2[$xx], "Applicants:"))        
			{
			
			$tdapplicants = parse_array($table_array2[$xx], "<td", "</td>");
			$applicants = parse_array($tdapplicants[1], "<b", "</b>");
			for($xapplicant=0; $xapplicant<count($applicants); $xapplicant++)
			{
			$applicants[$xapplicant]=strip_tags(trim($applicants[$xapplicant]));;
			 //echo $applicants[$xapplicant];
			 $req = $bdd->prepare('INSERT INTO agent(nomAgent) VALUES(:nom)');
		$req->execute(array(
			'nom' => $applicants[$xapplicant]
			));
			
			
			/*==============================================================*/
		/* INSERTION DANS LA TABLE APPLICANT                                     */
		/*==============================================================*/
			$req = $bdd->prepare('SELECT idAgent FROM agent WHERE nomAgent = :possesseur');
		 $req->execute(array('possesseur' => $applicants[$xapplicant]));

		$donnees = $req->fetch();
		 
		 //echo $data_array['idArticle'];

		//echo $data_array['idArticle'];
			
			/*==============================================================*/
		/* INSERTION DANS LA TABLE AVOIRAGENT                                     */
		/*==============================================================*/
		 $req = $bdd->prepare('INSERT INTO avoiragent(idArticle, idAgent) VALUES(:nom, :possesseur)');
			
		 $req->execute(array(
			'nom' => $data_array['idArticle'],
			'possesseur' => $donnees['idAgent']
			));
			
			}
			}		
		}
		   
		}	
?>


