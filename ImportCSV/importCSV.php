<?php 
// ===========================================================================================================================================================
// MODULE CLASS_IMPORTCSV.INC
// -----------------------------------------------------------------------------------------------------------------------------------------------------------
// CLASSE ImportCsv = Load CSV Files 
// -----------------------------------------------------------------------------------------------------------------------------------------------------------
// STRUCTURES DE DONNEES
//    CSV_Header = Header (columns identification)
//    CSV_Datas  = Datas Structure
//
// METHODES
// __ImportCsv()          = Class Constructor
// initImport()           = $CSV_Header and $CSV_Datas Initialize (call by class constructor)
// extractDatasFromFile($nomFichier, $delimiter, $readHeader, $startIndex, $lastIndex, $extractHeaderOnly=false, $listeIndexCols=NULL, $ColFilter=NULL, $Filter=NULL ) 
//                        = Effectue la lecture du fichier au format CSV
// loadHeader($nomFichier, $delimiter, $readHeader=true)           
//                        = Chargement de l'en-tête (récupération des noms de colonnes)
// loadImport($nomFichier, $delimiter, $readHeader=true, $startIndex=-1, $lastIndex=-1, $readHeaderOnly=false, $ColFilter=NULL, $Filter=NULL)           
//                        = Permet de charger le fichier des résultats au format 
// loadImportByCol($nomFichier, $delimiter, $readHeader=true, $startIndex=-1, $lastIndex=-1, $listeIndexCols, $ColFilter=NULL, $Filter=NULL)
//                        = Importation des données à partir d'un tableau de colonnes et en appliquant éventuellement un critère de sélection des enregistrements
// toUTF8($table)         = Effectue une conversion en UTF8 de la table fournie en paramètre  
// readHeader()           = Retourne un tableau des colonnes contenues dans la struture de données $CSV_Header
// findColumn($rowName)   = Recherche d'une colonne donnée dans la structure $CSV_Datas. Retourne l'index de la colonne
// countHeaderColumns()   = Retourne le nombre de colonnes de la structure $CSV_Datas
// recordsCount()         = Retourne le nombre de lignes contenus dans la structure $CSV_Datas
// extractArray($colList) = Extrait les valeurs de $CSV_Datas à partir d'un tableau de colonnes à extraire
// dataSort($colName, $TYPE_TRI=MAT_HS_SORT_STRING_COL)
//                        = Tri de la structure des données. Attention, dans ce cas, la structure $CSV_Datas est triée. 
// extractWithFilter($col, $condition)
//                        = Extraction des données à partir d'un tableau de conditions. Toutes les colonnes sont extraites
// displayHeader()        = Récupération des entêtes et formatage dans une structure de type <th></th>
// extractColumnValue($col, $value) = Extraction d'une valeur à partir d'une référence de cellule 
// toJson()               = Transfert la structure $CSV_Datas vers une structure au format JSON
// CONSTANTES
// CSV_SORT_STRING_COL       = 0 // TRI SUR UNE COLONNE DE TYPE STRING
// CSV_SORT_NUMERIC_COL      = 1 // TRI SUR UNE COLONNE DE TYPE NUMERIQUE
// CSV_SORT_REGULAR_COL      = 2 // TRI SUR UNE COLONNE REGULIERE
// CSV_SORT_LOCAL_STRING_COL = 3 // TRI SUR UNE COLONNE DE TYPE STRING EN RESPECTANT LA LOCALISATION
// -----------------------------------------------------------------------------------------------------------------------------------------------------------
// version 1.0.0 = intégration des sélection des occurrences multi-critères 
// version 1.0.1 = Changement du compteur du nombre d'occurrences dans la fonction ExtractDatasFromFiles / Le compteur renvoie le nombre réel d'occurrences
//                 retournée (Date de modification : 22/09/2015).
// 				   Changement structures des conditions pour les filtres :$filter=array("substr('@Nom',0,1)=='A'","'@Prénom'=='Manuela'"); est possible.  	
//                 ajout du paramétre $ORDRE=SORT_ASC dans la fonction dataSort() permettant de sélectionner le tri ascendant ou descendant.
//                 Ajout de la méthode toJson() permettant l'exportation des données vers une structure de type JSON
// version 1.0.2 = Correction du retour des occurrences par pagination.
// version 1.0.3 = correction du nombre maximal de colonnes à traiter (tableau des colonnes indexées)
// ===========================================================================================================================================================

// DEFINITION DES CONSTANTES DE VERSION DE LA CLASSE
if(!defined("ImportCSV_Version") || !defined("ImportCSV_DateRelease"))
{
	define ("ImportCSV_Version", "1.0.1");            // Current Release
	define ("ImportCSV_DateRelease", "09/25/2015");   // Release Date
}
else
{
	trigger_error("Class ImportCSV defined", E_USER_ERROR);
}

// DEFINTION DES CONSTANTES - ORDRE DES TRIS
define ("CSV_SORT_STRING_COL" , 0);       // TRI SUR UNE COLONNE DE TYPE STRING
define ("CSV_SORT_NUMERIC_COL" , 1);      // TRI SUR UNE COLONNE DE TYPE NUMERIQUE
define ("CSV_SORT_REGULAR_COL" , 2);      // TRI SUR UNE COLONNE REGULIERE
define ("CSV_SORT_LOCAL_STRING_COL" , 3); // TRI SUR UNE COLONNE DE TYPE STRING EN RESPECTANT LA LOCALISATION

// ----------------------------------------------------------------------------------
// Classe Lecture_Csv = Récupération des données dans un fichier plat avec séparateur
// ----------------------------------------------------------------------------------
class ImportCsv
{
	public $CSV_Header=array();       // EN TETE DES COLONNES DU FICHIER CSV = La structure doit comprendre la structure suivante : "Index", "Colonne", "Texte"
	public $CSV_Datas=array();        // LIGNES DES VALEURS ISSUES DU FICHIER CSV = La structure de tableau est fonction des données importées  
	
	function __ImportCsv()
	{
		$this->initImport();
	}
	
	// =================================================================================================================
	// FONCTION initImportation : REINITIALISE LES STRUCTURES DE DONNEES POUR UNE NOUVELLE IMPORTATION
	// =================================================================================================================
	function initImport()
	{
		$CSV_Header=array();
		$CSV_Datas=array();
	}

	// =================================================================================================================
	// FONCTION extractDatasFile = effectue la lecture du fichier au format CSV
	// -----------------------------------------------------------------------------------------------------------------
	// $nomFichier        = Nom complet du fichier à intégrer
	// $delimiter         = Caractère représentant le séparateur
	// $readHeader        = true: lecture de l'entête (colonnes) / false: Pas de lecture des entêtes
	// $startIndex        = Première occurrence à lire
	// $lastIndex         = Nombre d'occurrence à lire
	// $extractHeaderOnly = true : Extaction de l'en-tête uniquement / false: lecture intégrale du fichier
	// $ListeIndexCols    = array() comprenant les colonnes à extraire (NULL = toutes les colonnes seront intégrées)
	// $colFilter         = Array() : tableau des colonnes faisant parti du filtrage
	// $Filter            = Array() : Tableau des conditions pour chaque colonne (exemple: "substr('@Nom',0,1)=='A'")
	//                                ou plusieurs conditions:
	//									$colFilter=array("Nom", "Prénom");
	//									$filter=array("substr('@Nom',0,1)=='A'","trim('@Prénom')=='Manuela'");   
	// -----------------------------------------------------------------------------------------------------------------
	// Valeur retournée   = -2 : Fichier introuvable
	//                    = -3 : Erreur lors de la lecture du fichier 
	//                    >=0  : Lecture effectuée : nombre d'occurrences dans la structure de données
	// =================================================================================================================
	function extractDatasFromFile($nomFichier, $delimiter, $readHeader, $startIndex, $lastIndex, $extractHeaderOnly=false, $listeIndexCols=NULL, $ColFilter=NULL, $Filter=NULL )
	{
		$this->initImport();       // Initialisation des structures de données
		$indexColFilterCsv=NULL;   // Tableau des indexs de colonnes servant au filtrage
		$firstRec=($startIndex==-1)?0:$startIndex;
		$lastRec=($lastIndex==-1)? 99999999:$lastIndex;

		$CSV_Return=-1;            // FLAG DE RETOUR DE FONCTION
		$nbRows=0;                 // Nombre de lignes dans le fichier
		$compteurEnregTraites=0;   // Compteur du nombre d'occurrences traitées

		if(file_exists($nomFichier)===true)
		{
			// LECTURE DU FICHIER 
			if(($handle=fopen($nomFichier, "rb"))!=false)
			{
				// PARCOURS DES ENREGISTREMENTS
				while(($rowCsv=fgetcsv($handle,0,$delimiter))!=false)
				{						
					if($nbRows==0 && $readHeader===true)  // =====> TRAIEMENT DE LA LIGNE D'EN-TETE
					{
						// EXTRACTION DES DONNEES POUR LES FILTRES EVENTEULS
						if($ColFilter!=NULL && $Filter!=NULL)
						{							
							$indexColFilter=0;
							foreach($ColFilter as $selectFilter)
							{
								$indexRowFilterCsv=0;
								foreach($rowCsv as $ligneCsv)
								{
									if($selectFilter===utf8_encode($ligneCsv))
									{
										$indexColFilterCsv[$indexColFilter]=$indexRowFilterCsv;
										$indexColFilter++;
									}
									$indexRowFilterCsv++;
								}
							}
						}

						$indexCsvCol=0;                  // COMPTEUR DE COLONNES DANS LE FICHIER CSV
						$indexEnteteCol=0;               // COMPTEUR DE COLONNES DANS LA STRUCTURE DE MEMORISATION
						$indexListeCol=0;                // COMPTEUR DE COLONNES DANS LA STRUCTURE DE LA LISTE DES COLONNES A INTEGRER
						$indexColFilter=0;               // INDEX COURANT DANS LA LISTE DES COLONNES DU FILTRE

						if(is_null($listeIndexCols))     // TOUTES LES COLONNES SONT INCLUSES
						{
							foreach($rowCsv as $Cell)
							{
								$this->CSV_Header[$indexEnteteCol]=array( 	"Index" =>$indexCsvCol,
									"Colonne"=>utf8_encode($Cell),
									"Texte"=>utf8_encode($Cell),
																				"Visible"=>"O", //$cellEntete["Visible"],
																				"Smartphone"=>"O", //$cellEntete["SmartPhone"],
																				"IndexCsv"=>$indexCsvCol);    // MEMORISATION DES DONNEES
								$indexEnteteCol++;							
									$indexCsvCol++;     // INCREMENTATION DE LA COLONNE COURANTE DANS LA STRUCTURE DES COLONNES DU FICHIER CSV
								}
							}
						else                             // SEULES LES COLONNES CONTENUES DANS LE TABLEAU $listeIndexCols SERONT RETENUES 
						{
							$indexEnteteCol=0;
							foreach($listeIndexCols as $cellEntete)	
							{
								$indexCsvCol=0;
								foreach($rowCsv as $Cell)
								{
									if(utf8_encode($Cell)===$cellEntete["Colonne"]) // LA COLONNE DE LA LISTE DES COLONNES A RETENIR EST MEMORISEE
									{
										$this->CSV_Header[$indexEnteteCol]=array( 	"Index" =>$indexCsvCol,
											"Colonne"=>utf8_encode($cellEntete["Colonne"]),
											"Texte"=>$cellEntete["Texte"],
											"Visible"=>$cellEntete["Visible"],
																					"Smartphone"=>"O", //$cellEntete["SmartPhone"],
																					"IndexCsv"=>$indexCsvCol);    // MEMORISATION DES DONNEES
										$indexEnteteCol++;							
									}
									$indexCsvCol++; // INCREMENTATION DE LA COLONNE COURANTE DANS LA STRUCTURE DES COLONNES DU FICHIER CSV
								}
							}
						}
					}
					else                                 // =====> TRAITEMENT DES LIGNES DE DONNEES (AUTRES LIGNES)
					{
						if($extractHeaderOnly)
						{
							$nbRows=0;
							break;
						}

						$ATraiter=false; // Flag de ligne valide si =1
						if(!empty($ColFilter) && !empty($Filter) ) // APPLICATION DU FILTRE
						{
							if($indexColFilterCsv!=NULL)
							{				
								$nbConditions=0;							
								for($ii=0;$ii<count($indexColFilterCsv);$ii++)
								{
									$ColCondition=utf8_encode($rowCsv[$indexColFilterCsv[$ii]]);
									$temp=str_replace("@".$ColFilter[$ii], $ColCondition, "return(".$Filter[$ii].");");
									if(eval($temp))
									{
										$nbConditions++;
									}
								}
								if($nbConditions==count($indexColFilterCsv))
									$ATraiter=true;
							}
						}
						else
						{
							$ATraiter=true;
						}	
						if($ATraiter)
						{
							$borneMax=$firstRec+$lastRec-1;
							if($compteurEnregTraites>=$firstRec && $compteurEnregTraites<=$borneMax) 
							{
								if(count($this->CSV_Header)>1)
								{
									$ligneATraiter=array();
									foreach($this->CSV_Header as $colEntete)
									{
										if($colEntete!=null && $rowCsv!=null)
										{
											if($colEntete["Index"]<count($rowCsv))
												$ligneATraiter[]=$rowCsv[$colEntete["Index"]];
										}
									}
									$this->CSV_Datas[]=$ligneATraiter;
								}
								else
								{
									$this->CSV_Datas[]=$rowCsv;
								}
							}
							$compteurEnregTraites++;						
						}
						//if($compteurEnregTraites>($firstRec+$lastRec))
						//	break;
					}
					$nbRows++; // COMPTEUR DE LIGNES LORS DU PARCOURS DU FICHIER
				}
				fclose($handle);
				$CSV_Return=$compteurEnregTraites; // PRESENCE D'UNE IMPORATION EN COURS
			}
			else
				$CSV_Return=-3;
		}
		else
			$CSV_Return=-2;

		return $CSV_Return;
	}
	
	// =================================================================================================================
	// FONCTION loadHeader = chargement de l'en-tête (récupération des noms de colonnes)
    // -----------------------------------------------------------------------------------------------------------------
	// $nomFichier = Nom du fichier
	// $$delimiter = séprateur de champs à utiliser
	// $readHeader = true: lecture de l'entête par défaut (valeur optionnelle)
	// -----------------------------------------------------------------------------------------------------------------
	// Retourne le tableau des données
	// =================================================================================================================
	function loadHeader($nomFichier, $delimiter, $readHeader=true)
	{
		return $this->extractDatasFile($nomFichier, $delimiter, $readHeader, -1, -1);
	}
	
	// =================================================================================================================
	// FONCTION LoadImport : Permet de charger le fichier des résultats au format CSV 
    // -----------------------------------------------------------------------------------------------------------------
	// Nom du fichier : complet, avec le répertoire
	// Caractère séparateur pour les colonnes
	// Valeur de retour : >=0 = nb d'occurrence / -2 : fichier introuvable / -3 : pb lors de l'ouverture du fichier 
	// -----------------------------------------------------------------------------------------------------------------
	// Retourne le tableau des données
	// =================================================================================================================
	function loadImport($nomFichier, $delimiter, $readHeader=true, $startIndex=-1, $lastIndex=-1, $readHeaderOnly=false, $ColFilter=NULL, $Filter=NULL)
	{
		if($ColFilter=="")
			$ColFilter=NULL;
		return $this->extractDatasFromFile($nomFichier, $delimiter, $readHeader, $startIndex, $lastIndex, $readHeaderOnly,NULL,$ColFilter, $Filter);
	}
	
	// =================================================================================================================
	// FONCTION LoadImportByCol : Permet de charger le fichier des résultats au format CSV avec une liste de colonnes 
    // -----------------------------------------------------------------------------------------------------------------
	// Nom du fichier : complet, avec le répertoire
	// Caractère séparateur pour les colonnes
	// Valeur de retour : >=0 = nb d'occurrence / -2 : fichier introuvable / -3 : pb lors de l'ouverture du fichier 
	// -----------------------------------------------------------------------------------------------------------------
	// Retourne le tableau des données
	// =================================================================================================================
	function loadImportByCol($nomFichier, $delimiter, $readHeader=true, $startIndex=-1, $lastIndex=-1, $listeIndexCols, $ColFilter=NULL, $Filter=NULL)
	{
		return $this->extractDatasFromFile($nomFichier, $delimiter, $readHeader, $startIndex, $lastIndex, false,  $listeIndexCols, $ColFilter, $Filter);
	}
	
	// =================================================================================================================
	// FONCTION toUTF8 = Conversion du contenu complet en UTF8
    // -----------------------------------------------------------------------------------------------------------------
    // $table : Table à convertir
    // Retourne un tableau converti en UTF8
	// =================================================================================================================
	function toUTF8($table)
	{
		$arrayReturn=array();
		foreach($table as $row)
		{
			$arrayReturn[]=utf8_encode($row);
		}
		return $arrayReturn;
	}

	// =================================================================================================================
	// FONCTION readHeader = RETOURNE LES ENTETES DE COLONNES DANS UN ARRAY		
    // -----------------------------------------------------------------------------------------------------------------
	//  Retourne un tableau comprenant la structure des colonnes
	// =================================================================================================================
	function readHeader()
	{
		$arrayReturn=array();
		foreach($this->CSV_Header as $cell)
		{
			$arrayReturn []=$cell;
		}
		return $arrayReturn;
	}

	// =================================================================================================================
	// FONCTION findColumn : recherche une colonne dans la table.
	//                       retourne -1 si la valeur n'existe pas et son index si cette dernière existe.
	//                       retourne l'index de la colonne si cette dernière existe, sinon retourne -1		
	// =================================================================================================================
	function findColumn($rowName)
	{
		$Index=-1;
		$i=0;
		foreach($this->CSV_Header as $Key=>$Row)
		{
			if($Row["Colonne"]===$rowName)
			{
				$Index=$i;
				break;
			}
			$i++;	
		}
		return $Index;
	}
	
	// =================================================================================================================
	// FONCTION countColumnsHeader = retourne le nombre de colonnes d'en-têtes
    // -----------------------------------------------------------------------------------------------------------------
    // Retourne le nombre de colonnes des en-têtes
	// =================================================================================================================
	function countHeaderColumns()
	{
		if(isset($this->CSV_Header))
			return count($this->CSV_Header);
		else
			return 0;
	}

	// =================================================================================================================
	// FONCTION recordsCount = Retourne le nombre d'enregistrements contenus dans le tableau des données
    // -----------------------------------------------------------------------------------------------------------------
    // Retourne le nombre de ligne de la structure $CSV_Datas
	// =================================================================================================================
	function recordsCount()
	{
		if(isset($this->CSV_Datas))
			return count($this->CSV_Datas);
		else
			return 0;
	}
	
	// =================================================================================================================
	// FONCTION extractArray = Retourne un tableau de valeurs avec les colonnes contenues dans la structure $colList
    // -----------------------------------------------------------------------------------------------------------------
    // $colList = tableau de la liste des colonnes à retourner
	// =================================================================================================================
	function extractArray($colList)
	{
		$arrayResults=array(); // TABLEAU DE RECEPTION DES VALEURS
		
		// RECUPERATION DES INDEXS DES COLONNES CONTENUES DANS LE TABLEAU $colList
		$arrayHeader=array();
		foreach($colList as $header)
		{
			$i=$this->findColumn($header);
			if($i!=-1)
			{
				$arrayHeader[]=$i; 
			}
		}

		// LECTURE DES ENREGISTREMENTS DANS LE TABLEAU $CSV_Datas
		foreach($this->CSV_Datas as $Row)
		{
			$locArray=array();
			foreach($arrayHeader as $col)
			{
				$locArray[]=$Row[$col]; 
			}
			$arrayResults[]=$locArray;
		}
		return $arrayResults;
	}
	
	// =================================================================================================================
	// FONCTION dataSort($colName) : Effectue un tri sur une colonne du tableau
	// -----------------------------------------------------------------------------------------------------------------
	// ENTREE = $colName - Nom de la colonne
	//          $Type_Tri - Type de tri      
	//          $ORDRE = ordre de tri (SORT_ASC ou SORT_DESC)
	// SORTIE = Retourne TRUE si le tri est effectué. ATTENTION, cette fonction affecte l'ordre de la structure CSV_Datas                   
	// =================================================================================================================
	function dataSort($colName, $TYPE_TRI=CSV_SORT_STRING_COL, $ORDRE=SORT_ASC)
	{
		$bRetour=false;
		// Test de l'ordre du tri
		if($ORDRE==SORT_ASC)
		{
			$ordreTri=SORT_ASC;
		}
		else
		{
			$ordreTri=SORT_DESC;
		}
		$i=$this->findColumn($colName);
		$Table=$this->CSV_Datas;
		if($i!=-1)
		{
			$T_Sort=array();
			foreach ($this->CSV_Datas as $row) 
			{
				$T_Sort[]  = $row[$i];
			}
			$retour=false;		

			switch($TYPE_TRI)
			{
				case CSV_SORT_STRING_COL:
				array_multisort($T_Sort, $ordreTri, CSV_SORT_NUMERIC_COL, $this->CSV_Datas);
				$bRetour=true;
				break;
				case CSV_SORT_NUMERIC_COL:
				array_multisort($T_Sort, $ordreTri, CSV_SORT_NUMERIC_COL, $this->CSV_Datas);
				$bRetour=true;
				break;
				case CSV_SORT_LOCAL_STRING_COL:
				array_multisort($T_Sort, $ordreTri, CSV_SORT_STRING_COL, $this->CSV_Datas);
				$bRetour=true;
				break;
				default:
				array_multisort($T_Sort, $ordreTri, CSV_SORT_STRING_COL, $this->CSV_Datas);
				$bRetour=true;
				break;					
			}

		}
		return $bRetour;
	}
	
	// =================================================================================================================
	// FONCTION extractWithFilter = EXTRACTION DES DONNES SUR UN FILTRE DONNE
	// -----------------------------------------------------------------------------------------------------------------
	// ENTREE = $col       = colonne sur laquelle doit-être appliquée le filtre
	//          $condition = Condition de l'extraction
	// SORTIE = Tableau de données
	// =================================================================================================================
	function extractWithFilter($col, $condition)
	{
		// REFORMATAGE DE L'EXPRESSION
		$Filtre='$Row[$index]'.$condition;
		$Retour=array();
		$index=$this->findColumn($col);
		if($index!=-1)
		{
			foreach($this->CSV_Datas as $Row)
			{
				if($Filtre)
				{
					$Retour[]=$Row;
				}
			}
		}			
		return $Retour;
	}
	
	// =================================================================================================================
	// FONCTION displayHeader : lecture de l'entête (récupération des colonnes) uniquement
	// =================================================================================================================
	function displayHeader()
	{
		$Retour="";
		if(is_null($this->CSV_Header))
		{
			$Retour="pas entete";
		}
		else
		{
			$Retour.= '<thead><tr align=center>';
			foreach($this->CSV_Header as $libHeader)
			{
				if($libHeader["Visible"]=="O")
				{
					if($libHeader["Smartphone"]=="O")
					{
						$Retour.= '<th>' . $libHeader["Texte"] . '</th>';
					}
					else
					{
						$Retour.= '<th class="ColonneNoSmartphone">' . $libHeader["Texte"] . '</th>';
					}
				}
			}
			$Retour.= "</tr></thead>";
		}
		return $Retour;
	}

	// =================================================================================================================
	// extractColumnValue = Retourne l'index de l'occurrence dont la colonne et le contenu de la colonne correspondent
	// -----------------------------------------------------------------------------------------------------------------
	// ENTREE =  $col   = Nom de la colonne où sera effectuée la recherche
	//           $Value = Valeur à chercher
	// 
	// SORTIE = Index de la ligne du table CSV_Datas. en cas de retour négative = recherche infructueuse.
	// =================================================================================================================
	function extractColumnValue($col, $value)
	{
		$index=$this->findColumn($col);
		$retour = array_search(trim($value), array_column($this->CSV_Datas, $index), true);
		if($retour==FALSE)
			$retour=-1;
		return $retour;
	}

	// =================================================================================================================
	// toJson = Transfert la structure $CSV_Datas vers une structure au format JSON
	// -----------------------------------------------------------------------------------------------------------------
	// ENTREE =  - Utilise la structure de données $CSV_Datas
	// 
	// SORTIE = Flux au format JSON
	// =================================================================================================================
	function toJson()
	{
		$retour="";

		$retour= json_encode($this->CSV_Header);
		return $retour;
	}
}
?>