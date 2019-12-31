<?php

	//TODO Übersetzung der Sprachgebiete in den Info-Windows funktioniert zumindest in der 161 nicht
	function load_va_data (){
		try {
			set_error_handler(function ($severity, $message, $file, $line){
				throw new ErrorException($message, 0, $severity, $file, $line);
				//TODO get this running, especially with wordpress db errors
			});
			
			global $Ue;
			$result = new IM_Result($_POST['key']);
	
			$db = IM_Initializer::$instance->database;
	
			$lang = strtoupper(substr($_POST['lang'], 0, 1));
			
			$epsilon = 0;
			$grid_category = NULL;
			if(isset($_POST['hexgrid'])){
				$pos_pipe = strpos($_POST['hexgrid'], '|');
				$epsilon = substr($_POST['hexgrid'], $pos_pipe + 1);
				$grid_category = substr($_POST['hexgrid'], 1, $pos_pipe - 1);
			}
			
			switch ($_POST['category']){
				case 0: //Informanten
					
					if($epsilon === 0){
						if($_POST['community'] == 'true'){
							$geo_sql = '(SELECT Mittelpunkt FROM Orte WHERE Id_Ort = Id_Gemeinde)';
						}
						else {
							$geo_sql = 'Georeferenz';
						}
					}
					else {
						$geo_sql = $db->prepare('(SELECT Center FROM Z_Geo WHERE Id_Geo = Id_Polygon AND Epsilon = %f)', $epsilon);
					}
					
					//Cannot come from zgeo, since informant nets without records should still be shown!
					
					if($grid_category !== NULL){
						$query = "
						SELECT Ortsname, Nummer, AsText(" . $geo_sql. ") as Geo, Id_Informant, Id_Polygon
						FROM Informanten JOIN A_Informant_Polygon USING (Id_Informant)
						WHERE Erhebung = %s" . ($_POST['outside'] == 'false' ? ' and Alpenkonvention' : '') . ' AND Id_Kategorie = %d
						GROUP BY Id_Informant
						ORDER BY Id_Polygon, Position';
						
						$data = $db->get_results($db->prepare($query, substr($_POST['key'], 1), $grid_category), ARRAY_A);
						
						$last_poly = -1;
						$last_id = -1;
						$last_geo = NULL;
						$info_windows = [];
						foreach ($data as $row){
							if ($row['Id_Polygon'] !== $last_poly) {
								if(count($info_windows) > 0){
									$result->addMapElements(-1, $info_windows, $last_geo, NULL, va_get_quantify_data_informant($last_id, $db));
								}
								
								$last_poly = $row['Id_Polygon'];
								$last_geo = $row['Geo'];
								$last_id = $row['Id_Informant'];
								$info_windows = [];
							}
							
							$info_windows[] = (isset($_POST['editMode'])?
									new IM_EditableElementInfoWindowData($row['Id_Informant'], va_get_edit_data_informant($row['Id_Informant'], $db)) :
									new IM_SimpleElementInfoWindowData($row['Ortsname'], $row['Nummer']));
						}
						if(count($info_windows) > 0){
							$result->addMapElements(-1, $info_windows, $last_geo, NULL, va_get_quantify_data_informant($last_id, $db));
						}
						
					}
					else {
						$query = "
						SELECT Ortsname, Nummer, AsText(" . $geo_sql. ") as Geo, Id_Informant
						FROM Informanten JOIN A_Informant_Polygon USING (Id_Informant)
						WHERE Erhebung = %s" . ($_POST['outside'] == 'false' ? ' and Alpenkonvention' : '') . '
						GROUP BY Id_Informant
						ORDER BY Position';
						
						$data = $db->get_results($db->prepare($query, substr($_POST['key'], 1)), ARRAY_A);
						
						foreach ($data as $row){
							$result->addMapElement(
									-1,
									(isset($_POST['editMode'])?
											new IM_EditableElementInfoWindowData($row['Id_Informant'], va_get_edit_data_informant($row['Id_Informant'], $db)) :
											new IM_SimpleElementInfoWindowData($row['Ortsname'], $row['Nummer'])),
									$row['Geo'],
									NULL,
									va_get_quantify_data_informant($row['Id_Informant'], $db)
									);
						}
					}
				break;
				
				case 1: //Concept
					if($_POST['filter']['conceptIds'] == 'ALL'){
						$concepts = $db->get_col($db->prepare('SELECT Id_Konzept FROM A_Ueberkonzepte_Erweitert WHERE Id_Ueberkonzept = %d', substr($_POST['key'], 1)));
						$concepts[] = substr($_POST['key'], 1);
					}
					else if(empty($_POST['filter']['conceptIds']))
						break;
					else {
						$concepts = $_POST['filter']['conceptIds'];
					}
						
					$where_clause = $db->prepare("Id_Instance IN (SELECT DISTINCT Id_Instance FROM Z_Ling WHERE Id_Concept IN " . im_key_placeholder_list($concepts) . ')', $concepts);
					va_create_result_object($where_clause, $lang, $epsilon, $grid_category, $result, $Ue, $db);
				break;
				
				case 2: //Phonetic Type
				case 3: //Morphologic Type
					$where_clause = $db->prepare("Id_Instance IN (SELECT DISTINCT Id_Instance FROM Z_Ling WHERE Type_Kind = '" . $_POST['key'][0] . "' AND Id_Type = %d)", substr($_POST['key'], 1));
					va_create_result_object($where_clause, $lang, $epsilon, $grid_category, $result, $Ue, $db);
				break;
				
				case 4: //Base Type
					$where_clause = $db->prepare("Id_Instance IN (SELECT DISTINCT Id_Instance FROM Z_Ling WHERE Id_Base_Type = %d)", substr($_POST['key'], 1));
					va_create_result_object($where_clause, $lang, $epsilon, $grid_category, $result, $Ue, $db);
				break;
				
				case 5: //Extralinguistic
				case 6: //Polygons
					
					$id_cat = substr($_POST['key'], 1);
					
					$pos_col = strpos($id_cat, '|');
					if($pos_col !== false){
						$id_cat = substr($id_cat, 0, $pos_col); //Epsilon can be ignored, since it is set in $_POST['hexgrid'] before loading!
					}
					
					if(isset($_POST['hexgrid']) && !va_is_hex_category($id_cat, $_POST['category'])){
						return new IM_Error_Result('Not possible in hexagon mode!');
					}
					
					//Community flag is ignored, since it does not make sense for most of the extralinguistic data.
					if(isset($_POST['filter']['tags'])){ //Tag filter
						$tagsNeeded = $_POST['filter']['tags'];
						
						$whereClause = $db->prepare('Id_Geo IN (SELECT Id_Ort
									FROM 
										A_Tag_Werte
									WHERE 
										Id_Kategorie = %d AND
										CASE Tag
								', $id_cat);
						
						foreach ($tagsNeeded as $tag => $values){
							$whereClause .= $db->prepare('WHEN %s THEN ', $tag);
							
							if(in_array('EMPTY', $values)){
								$whereClause .= 'Wert IS NULL OR ';
								$values = array_filter($values, function ($e){return $e != 'EMPTY';});
							}
							
							$whereClause .= $db->prepare('Wert IN ' . im_string_placeholder_list($values) . ' ', $values);
						}
						
						$whereClause .= $db->prepare(' END
									GROUP BY Id_Ort
									HAVING count(*) = (SELECT count(DISTINCT Tag) FROM a_kategorie_tag_werte WHERE Id_Kategorie = %d))', $id_cat);
					}
					else {
						$whereClause = $db->prepare('Id_Category = %s', $id_cat);
					}
					
					$default_epsilons = array ('60' => 0.001, '62' => 0.0006, '1' => 0.005, '17' => 0.002, '63' => 0.003);
					
					if (isset($_POST['simple_polygons']) && $_POST['simple_polygons'] == 'true' && $epsilon === 0 && isset($default_epsilons[$id_cat])){
						$epsilon = $default_epsilons[$id_cat];
					}
					
					//TODO if default-epsilon is set but no simplified polygon exists for a certain polygon nothing is loaded!!!
					
					//TODO split areas and extra-ling and move code for tags etc. to functions
					
					if($_POST['category'] == 5){ //ExtraLing
						if($grid_category === NULL){
							$geo_sql = 'Geo_Data';
						}
						else {
							$geo_sql = $db->prepare('
								(SELECT Center 
								FROM Z_Geo z2 JOIN A_Ort_Polygon a2 ON z2.Id_Geo = a2.Id_Polygon  AND a2.Id_Kategorie = %d
								WHERE a2.Id_Ort = z1.Id_Geo AND z2.Epsilon = %f)', $grid_category, $epsilon);
						}
						
						$query = "
						SELECT Name, Description, astext(" . $geo_sql . "), Tags, Id_Geo, GROUP_CONCAT(CONCAT(Id_Kategorie, ':', Id_Polygon)), ContainsTranslations, Cluster_Id, AsText(ST_Envelope(Geo_Data))
						FROM Z_Geo z1 LEFT JOIN A_Ort_Polygon ON Id_Geo = Id_Ort
						WHERE " . $whereClause . (!isset($_POST['outside']) || $_POST['outside'] == 'false' ? ' and Alpine_Convention' : '') . ' AND Epsilon = 0
						GROUP BY Id_Geo
						ORDER BY Cluster_Id ASC';
						$data = $db->get_results($query, ARRAY_N);
					}
					else { //Areas
						$query = "
						SELECT Name, Description, astext(Geo_Data), Tags, Id_Geo, '' AS Quant, ContainsTranslations, Cluster_Id, AsText(ST_Envelope(Geo_Data))
						FROM Z_Geo
						WHERE " . $whereClause . (!isset($_POST['outside']) || $_POST['outside'] == 'false' ? ' and Alpine_Convention' : '') . ' AND Epsilon = %f
						GROUP BY Id_Geo
						ORDER BY Cluster_Id ASC';
						$data = $db->get_results($db->prepare($query, $epsilon), ARRAY_N);
					}
					
					
	
					//Compute categories for records:
					if(isset($_POST['filter']) && $_POST['filter']['subElementCategory'] == -1){ //Pseudo category
						$subCategoryId = 0;
						foreach ($data as $key => $row){
							$data[$key][9] = '?' . $subCategoryId++;
						}
					}
					else if(isset($_POST['filter']) && $_POST['filter']['subElementCategory'] == -3){ //Tags
						foreach ($data as $key => $row){
							$subVal = -1;
							$tagArray = json_decode($row[3], true);
							if($tagArray != NULL && isset($tagArray[$_POST['filter']['selectedTag']])){
								$subVal = "#" .$tagArray[$_POST['filter']['selectedTag']];
							}
							$data[$key][9] = $subVal;
						}
					}
					else if (isset($_POST['filter']['subElementCategory']) && $_POST['filter']['subElementCategory'] == -4){ //All distinct
						foreach ($data as $key => $row){
							$data[$key][9] = "$" . $db->get_var('SELECT Farbe FROM Orte_Faerbung WHERE Id_Ort = ' . $row[4]);
						}
					}
					else {
						foreach ($data as $key => $row){
							$data[$key][9] = '-1';
						}
					}
					
					usort($data, function ($a, $b){
						$cat = strcmp($a[9], $b[9]);
						
						if($cat == 0){
							return $a[7] - $b[7];
						}
						return $cat;
					});
					
					//Add records:
					$last_id = -1;
					$last_cat = -1;
					$current_windows = NULL;
					$last_geo_data = NULL;
					$last_quant_data = NULL;
					foreach ($data as $row){
						if($row[7] == -1){
							if($current_windows != NULL){
								//Has to be a point symbol => most of the special cases treated in va_add_extra_ling_element can be omitted
								$result->addMapElements($last_cat, $current_windows, $last_geo_data, NULL, $last_quant_data);
								$current_windows = NULL;
							}
							
							//No clusterung => directly add record
							va_add_extra_ling_element(
								$db,
								$result,
								$row[9],
								va_extra_ling_info_window($_POST['category'], $row, $Ue, $lang),
								$row[2],
								$row[8]? va_format_bounding_box($row[8]): null,
								$row[4],
								$row[5],
								$epsilon);
						}
						else if ($row[7] != $last_id || $last_cat != $row[9]){
							if($current_windows != NULL){
								//Has to be a point symbol => most of the special cases treated in va_add_extra_ling_element can be omitted
								$result->addMapElements($last_cat, $current_windows, $last_geo_data, NULL, $last_quant_data);
							}
							$current_windows = array(va_extra_ling_info_window($_POST['category'], $row, $Ue, $lang));
							$last_id = $row[7];
							$last_cat = $row[9];
							$last_geo_data = $row[2];
							$last_quant_data = va_get_quantify_data_extra_ling($row[5]);
						}
						else {
							$current_windows[] = va_extra_ling_info_window($_POST['category'], $row, $Ue, $lang);
						}
					}
					if($current_windows != NULL){
						//Has to be a point symbol => most of the special cases treated in va_add_extra_ling_element can be omitted
						$result->addMapElements($last_cat, $current_windows, $last_geo_data, NULL, $last_quant_data);
					}
				break;
				
				case 7: //SQL
					$where = stripslashes($_POST['filter']['where']);
					
					if(strpos($where, ';') !== false){
						return new IM_Error_Result('No semicolons permitted!');
					}
					
					$max_points = 2000;
					
					$db->hide_errors();
					
					$num = $db->get_var('SELECT count(DISTINCT Id_Instance) FROM z_ling WHERE ' . $where);
					
					if($db->last_error != ''){
						return new IM_Error_Result($db->last_error);
					}
					
					if($num > $max_points){
						return new IM_Error_Result('Two many points: ' . $num . '. Maximum is ' . $max_points . '.');
					}
					
					va_create_result_object('Id_Instance IN (SELECT DISTINCT Id_Instance FROM z_ling WHERE ' . $where . ')', $lang, $epsilon, $grid_category, $result, $Ue, $db);
					
					if($db->last_error != ''){
						return new IM_Error_Result($db->last_error);
					}
					
					break;
			}

			return $result;
		}
		catch (ErrorException $exception){
			return new IM_Error_Result($exception);
		}
	}

function va_extra_ling_info_window ($category, $row, $Ue, $lang){
	
	
	if($row[6] == '0'){ //No translations
		$name = $row[0];
		$descr = $row[1];
	}
	else {
		$name = va_translate_content(va_translate_extra_ling_name($row[0], $lang), $Ue);
		$descr = va_translate_content($row[1], $Ue);
	}
	
	if ($category == 6){
		$id = $row[4];
		return new IM_PolygonInfoWindowData($name, $descr, $id);
	}
	else {
		return new IM_SimpleElementInfoWindowData($name, $descr);
	}
}
	
function va_translate_extra_ling_name ($name, $lang){
	//Check potential name translations:
	$name_list = explode('###', $name);
	$oname = $name_list[0];
	unset($name_list[0]);
	foreach ($name_list as $curr_oname){
		if($curr_oname[0] === $lang){
			$oname = mb_substr($curr_oname, 2);
			break;
		}
	}
	return $oname;
}
	
function va_add_extra_ling_element (&$db, &$result, $subVal, $info, $geo, $bounding_box, $id, $poly, $epsilon){
	
	if (isset($_POST['filter']['onlyMultipolygons']) && strpos($geo, 'MULTIPOLYGON') !== 0){
		return;
	}
	
	if (isset($_POST['filter']['centerOutsideContour']) && 
			($db->get_var('SELECT WITHIN(Mittelpunkt, Geodaten) AND ST_WITHIN(Mittelpunkt, Geodaten) FROM Orte WHERE Id_Ort = ' . $id) == '1'
			|| strtolower($info->getName()) == 'water body')){
		return;
	}
	
	if (isset($_POST['filter']['addCenterPoints'])){
		$result->addMapElement(
				$subVal, 
				new IM_SimpleElementInfoWindowData('Mittelpunkt ' . $info->getName(), ''),
				$db->get_var($db->prepare('SELECT AsText(Center) FROM Z_Geo WHERE Id_Geo = ' . $id . ' AND Epsilon = %f', $epsilon)),
				NULL);
	}
	
	$quant_data = NULL;
	if (strpos($geo, 'POLYGON') === 0 || strpos($geo, 'MULTIPOLYGON') === 0){
		$quant_data = new IM_Polygon_Quantify_Info($id);
	}
	else {
		$quant_data = va_get_quantify_data_extra_ling($poly);
	}

	$result->addMapElement($subVal, $info, $geo, $bounding_box, $quant_data);
}

function va_translate_content ($text, &$Ue){
	if(isset($Ue[$text])){
		return $Ue[$text];
	}
	
	$text = preg_replace_callback('/Ue\[([^\]]*)\]/', function ($matches) use (&$Ue){
		if(isset($Ue[$matches[1]])){
			return $Ue[$matches[1]];
		}
		return $matches[1];
	}, $text);
	return $text;
}

function va_create_result_object ($where_clause, $lang, $epsilon, $grid_cat, IM_Result &$result, &$Ue, &$db){
	$bibData = [];
	$stimulusData = [];
	
	$query = va_create_record_query($where_clause, $epsilon, $grid_cat, $db);
	$db->query('SET SESSION group_concat_max_len = 100000');
	$dbresult = $db->get_results($query, ARRAY_N);
	
	if(isset($_POST['filter']['subElementCategory'])){
		$subElementType = intval($_POST['filter']['subElementCategory']);
	}
	else {
		$subElementType = NULL;
	}
	
	if($subElementType === 1){ //Concept
		$concept_mapping = va_build_concept_mapping($where_clause, $db);			
	}
	
	$map_data = array();
	foreach ($dbresult as $row){
		$va_sub = '-1'; //The group for the selected record
		$current_array = array($row[0]); //Beleg
		
		$typifications = explode('-+-', $row[1]);
		$type_array = array();
		if($typifications[0] !== ''){
			foreach ($typifications as $t){
				$type_info = mb_split('#', $t);
				$kind = $type_info[0];
				$id = $type_info[1];
				if($kind == 'P')
					$type = $type_info[2];
				else
					$type = va_format_lex_type($type_info[2], $type_info[3], $type_info[4], $type_info[5], $type_info[6]); //util/tools.php
				$source = $type_info[7];
				$ref = $type_info[8];
				
				//For multiple references
				$type_exists = false;
				foreach ($type_array as &$type_descr){
					if($type_descr[0] == $kind && $type_descr[1] == $type && $type_descr[2] == $source){
						if($ref)
							$type_descr[3][] = $ref;
						$type_exists = true;
						break;
					}
				}
				if(!$type_exists)
					$type_array[] = array ($kind, $type, $source, ($ref? [$ref]: []));
				
				if($source == 'VA'){
					if($subElementType === 3) { //Lex. Typ
						if(($kind == 'L')){
							$va_sub = 'L' . $id;
						}
					}
					else if ($subElementType === 2) { //Phon. Typ
						if($kind == 'P'){
							$va_sub = 'P'. $id;
						}
					}
				}
				else if (($row[0] == '' || strpos($row[0], '###') === 0) && strpos($row[4], $source) === 0){
					//An empty record cannot be used, since the tryMerge function of the record window needs information
					$current_array[0] = $kind . 'TYP' . $type . $row[0];
				}
			}
		}
		
		$base_array = array();
		if($row[2] != ''){
			$base_types = explode('-+-', $row[2]);
			foreach ($base_types as $b){
				$posHash1 = mb_strpos($b, '#');
				$posHash2 = mb_strpos($b, '#', $posHash1 + 1);
				
				$id_btyp = mb_substr($b, 0, $posHash1 - 2); //-2 to remove |0 or |1 for unsure
				if($posHash2 === false){
					$btyp = mb_substr($b, $posHash1 + 1);
					$base_array[] = [$btyp, []];
				}
				else {
					$btyp = mb_substr($b, $posHash1 + 1, $posHash2 - $posHash1 - 1);
					$ref = mb_substr($b, $posHash2 + 1);
					
					//For multiple base type references
					$btypeExists = false;
					foreach ($base_array as &$btype_descr){
						if($btype_descr[0] == $btyp){
							if($ref)
								$btype_descr[1][] = $ref;
							$btypeExists = true;
							break;
						}
					}
					
					if(!$btypeExists)
						$base_array[] = [$btyp, ($ref? [$ref]: [])];
				}
				if($subElementType === 4) { //Basistyp
					if($va_sub === '-1'){
						$va_sub = 'B' . $id_btyp;
					}
					else {
						$va_sub .= '+B' . $id_btyp; //Concatenate multiple base type ids with plus
					}
				}
			}
		}
		
		$conceptArray = $row[3]? explode(',', $row[3]): array();
		if($subElementType === 1 && !empty($conceptArray)){ //Concept
			//TODO unterschiedliche TL-Konzepte
			$va_sub = 'C' . $concept_mapping[$conceptArray[0]];
		}
		
		if($subElementType === -3){ //Tags
			switch ($_POST['filter']['selectedTag']){
				case 'ERHEBUNG':
					$va_sub = '#' . substr($row[4], 0, strpos($row[4], ':'));
					break;
			}
		}
		
		$current_array[] = va_create_type_table($type_array, $base_array, $lang, $row[4], $Ue, strpos($row[0], '###') !== false);
		
		$current_array[] = $conceptArray;
		
		//Source:
		$sdata = explode('#', $row[4]);
		$atlas = $sdata[0];
		list($code, $html) = va_create_bibl_html($atlas);
	    
	    if(!in_array($atlas, $bibData)){
	    	$bibData[$atlas] = "<div id='$code' style='display: none;'>" . va_format_bibliography($row[13], $row[14], $row[15], $row[16], $row[17], $row[18], $row[19], $row[20], $row[21]) . "</div>";
	    }
	    
	    $key = $row[11] . '_' . $row[7];
	    if(!in_array($key, $stimulusData)){
	    	$res = '<div id="sti' . $key . '">' . $row[12];
	    	
	    	$link = va_produce_external_map_link($atlas, $sdata[1], $sdata[2], $sdata[3]);
	    	if($link){
	    		$res .= '<br /><br />' . $link;
	    	}
	    	
	    	$res .= '</div>';
	    	
	    	$stimulusData[$key] = $res;
	    }
	    
	    $html .= ' <span class="stimulus" data-stimulus="' . $key . '" style="text-decoration: underline; cursor: pointer;">' . $sdata[1] . '#' . $sdata[2] . '</span> ';

	    $current_array[] = $html . $sdata[3] . ' (' . $sdata[4] . ')';
		
		//community
		$community_names = explode('###', $row[6]);
		$cname = $community_names[0];
		unset($community_names[0]);
		foreach ($community_names as $curr_cname){
			if($curr_cname[0] === $lang){
				$cname = mb_substr($curr_cname, 2);
			}
		}
		$current_array[] = $cname;
		
		$current_array[] = $row[8]; //original
		
		$current_array[] = $row[9]; //encoding
		
		$markingColor = -1;
		if(isset($_POST['filter']['markings'])){
			switch ($_POST['filter']['markings']['tagName']){
				case 'ERHEBUNG':
					foreach ($_POST['filter']['markings']['tagValues'] as $key => $color){
						if(strtolower($key) == strtolower(substr($row[4], 0, strpos($row[4], '#')))){
							$markingColor = $color;
							break;
						}
					}
					break;
			}
		}

		$map_data[] = array($va_sub, new IM_RecordInfoWindowData($current_array), $row[5], va_get_quantify_data_informant($row[7], $db), $row[10], $markingColor);
	}
	
	usort($map_data, function ($a, $b){
		$cat = strcmp($a[0], $b[0]);
			
		if($cat == 0){
			$diff = intval($a[4]) - intval($b[4]); //Geodata ID
			
			if($diff == 0){
				return intval($a[5]) - intval($b[5]); //Marking color
			}
			
			return $diff;
		}
		return $cat;
	});
	
	$last_id = -1;
	$last_cat = -1;
	$last_mcolor = -1;
	$last_geo_data = NULL;
	$last_quantify_data = NULL;
	$current_windows = NULL;
	foreach ($map_data as $row){
		if($row[4] == -1){ //No clusterung => directly add record
			if($current_windows != NULL){
				$result->addMapElements($last_cat, $current_windows, $last_geo_data, NULL, $last_quantify_data, $last_mcolor);
				$current_windows = NULL;
			}
			
			$result->addMapElement($row[0], $row[1], $row[2], NULL, $row[3], $row[5]);
		}
		else if ($row[4] != $last_id || $last_cat != $row[0] || $last_mcolor != $row[5]){
			if($current_windows != NULL){
				$result->addMapElements($last_cat, $current_windows, $last_geo_data, NULL, $last_quantify_data, $last_mcolor);
			}
			$current_windows = [$row[1]];
			$last_id = $row[4];
			$last_cat = $row[0];
			$last_mcolor = $row[5];
			$last_quantify_data = $row[3];
			$last_geo_data = $row[2];
		}
		else {
			$current_windows[] = $row[1];
		}
	}
	if($current_windows != NULL){
		$result->addMapElements($last_cat, $current_windows, $last_geo_data, NULL, $last_quantify_data, $last_mcolor);
	}
	
	$result->addExtraData('BIB', $bibData);
	$result->addExtraData('STI', $stimulusData);
}

function va_get_quantify_data_informant ($id_informant, &$db){
	$res = new IM_Point_Quantify_Info();
			
	$dbdata = $db->get_results($db->prepare('SELECT Id_Kategorie, Id_Polygon FROM A_Informant_Polygon WHERE Id_Informant = %d', $id_informant), ARRAY_A);
	
	foreach ($dbdata as $row){
		$res->addCategoryIndex('A' . $row['Id_Kategorie'], $row['Id_Polygon']);
	}
	
	return $res;
}

function va_get_quantify_data_extra_ling ($poly){
	$res = new IM_Point_Quantify_Info();
	
	if($poly){
		$polies = explode(',', $poly);
		
		foreach ($polies as $entry){
			$edata = explode(':', $entry);
			$res->addCategoryIndex('A' . $edata[0], $edata[1]);
		}
	}

	return $res;
}

function va_get_edit_data_informant ($id_informant, &$db){	
	return $db->get_row($db->prepare('SELECT Erhebung, Nummer, Ortsname, Bemerkungen FROM Informanten WHERE Id_Informant = %d', $id_informant), ARRAY_A);
}

function va_build_concept_mapping ($where_clause, &$db){
	//Pre-compute used concepts according to the following convention:
	//	- In principle the top-level concept is used
	//	- If all records connected to a certain top-level concept also belong to a "lower" concept, that one is used
	
	$top_level_concept_list = array();
	$id_list = $db->get_results('SELECT DISTINCT Id_Concept, conceptDepth(Id_Concept) FROM Z_Ling WHERE Id_Concept IS NOT NULL AND ' . $where_clause, ARRAY_N);
	
	//Find top-level concepts for all concepts
	foreach ($id_list as $cid){
		$top_level_concept = $db->get_var('SELECT a.Id_Ueberkonzept FROM A_Ueberkonzepte_Erweitert a JOIN Ueberkonzepte u ON a.Id_Ueberkonzept = u.Id_Konzept WHERE u.Id_Ueberkonzept = 707 AND a.Id_Konzept = ' . $cid[0], 0, 0);
		if(!$top_level_concept) //Concept itself is top-level
			$top_level_concept = $cid[0];
		if(isset($top_level_concept_list[$top_level_concept])){
			$top_level_concept_list[$top_level_concept][] = $cid;
		}
		else {
			$top_level_concept_list[$top_level_concept] = array($cid);
		}
	}
	
	//"Downgrade" concepts
	$concept_mapping = array(); 
	foreach ($top_level_concept_list as $clist){
		if(count($clist) == 1){
			$concept_mapping['C' . $clist[0][0]] = $clist[0][0]; //Use concept itself
		}
		else {
			//Find highest concept
			$min_level = 9999;
			$min_concept = 0;
			$multiple_concepts = false;
			foreach ($clist as $centry){
				$cid = $centry[0];
				$level = $centry[1];				
				if($level == $min_level && $cid != $min_concept){
					$multiple_concepts = true;
				}
				else if ($level < $min_level){
					$min_level = $level;
					$min_concept = $cid;
					$multiple_concepts = false;
				}
			}

			if($multiple_concepts){ //Use next-higher concept
				$lowest_possible = $db->get_var('SELECT Id_Ueberkonzept FROM Ueberkonzepte WHERE Id_Konzept = ' . $min_concept, 0, 0);
			}
			else { //Use concept itself
				$lowest_possible = $min_concept;
			}
			
			foreach ($clist as $centry){
				$concept_mapping['C' . $centry[0]] = $lowest_possible;
			}
		}
	}
	return $concept_mapping;
}

function va_create_record_query ($where_clause, $epsilon, $grid_cat, &$db){
	
	if($epsilon === 0){
		if($_POST['community'] == 'true'){
			$geo_sql = 'Community_Center';
		}
		else {
			$geo_sql = 'Geo_Data';
		}
	}
	else {
		$geo_sql = $db->prepare('(SELECT AsText(Center) FROM Z_Geo WHERE Id_Geo = Id_Polygon AND Epsilon = %f)', $epsilon);
	}
	
	if($grid_cat === NULL){
		$cluster_id = 'Cluster_Id';
		$where_app = '';
	}
	else {
		$cluster_id = 'Id_Polygon';
		$where_app = $db->prepare(' AND Id_Kategorie = %d', $grid_cat);
	}
	
	return "SELECT
				Instance,
				GROUP_CONCAT(DISTINCT CONCAT(Type_Kind, '#', Id_Type, '#', Type, '#', Type_Lang, '#', POS, '#', Gender, '#', Affix, '#', Source_Typing, '#', IF(Type_Reference IS NULL, '', Type_Reference)) SEPARATOR '-+-') AS Typings,
				GROUP_CONCAT(DISTINCT CONCAT(Id_Base_Type, '|', Base_Type_Unsure, '#', IF(Base_Type_Unsure, '(?) ', ''), Base_Type, '#', IF(Base_Type_Reference IS NULL, '', Base_Type_Reference)) SEPARATOR '-+-') AS Base_Types,
				GROUP_CONCAT(DISTINCT CONCAT('C', Id_Concept)) AS Concepts,
				Instance_Source,
				" . $geo_sql . " AS Geo_Data,
				Community_Name,
				Id_Informant,
				Instance_Original,
				Instance_Encoding,
				" . $cluster_id . ",
				Id_Stimulus,
				Stimulus,
				Autor, 
				Titel, 
				Ort, 
				Jahr, 
				Download_URL, 
				Band, 
				Enthalten_In, 
				Seiten, 
				Verlag
			FROM Z_Ling JOIN A_Informant_Polygon USING (Id_Informant) JOIN Stimuli USING (Id_Stimulus) JOIN Bibliographie ON Erhebung = Abkuerzung
			WHERE " . $where_clause 
					. ($_POST['outside'] == 'false' ? ' AND Alpine_Convention' : '') .
					$where_app. "
			GROUP BY Id_Instance";
}

function va_create_type_table (&$types, &$btypes, $lang, $source, &$Ue, $part_of_group){

	$result = '<table class="easy-table easy-table-default va_type_table">';
	
	$va_phon_index = false;
	$va_lex_index = false;
	$source_phon_index = false;
	$source_lex_index = false;
	
	$phon_indexes = array();
	$morph_indexes = array();
	
	//Look for VA-Typings and Source-Typings
	foreach ($types as $index => $type){
		if($type[0] == 'P'){
			if($type[2] == 'VA'){
				$va_phon_index = $index;
			}
			else if(mb_strpos($source, $type[2]) === 0) {
				$source_phon_index = $index;
			}
			$phon_indexes[] = $index;
		}
		else {
			if($type[2] == 'VA'){
				$va_lex_index = $index;
			}
			else if(mb_strpos($source, $type[2]) === 0) {
				$source_lex_index = $index;
			}
			$morph_indexes[] = $index;
		}
	}
	
	//Phonetic types
	if($source_lex_index === false)
		$result .= va_get_type_table_row ($Ue['PHON_TYP'], $Ue['NICHT_TYPISIERT'], $va_phon_index, $source_phon_index, $phon_indexes, $types, $Ue['QUELLE'], $part_of_group);
	
	//Morphologic types
		$result .= va_get_type_table_row ($Ue['MORPH_TYP'], $Ue['NICHT_TYPISIERT'], $va_lex_index, $source_lex_index, $morph_indexes, $types, $Ue['QUELLE'], $part_of_group);
	
	//Base types
	if(count($btypes) == 0){
		$btype = $Ue['NICHT_TYPISIERT'];
	}
	else {
		$btype = implode(' + ', array_map(function ($btype){
			return add_references($btype[0], $btype[1]);
		}, $btypes));
	}
	$result .= '<tr><td>' . (count($btypes) > 1 ? $Ue['BASISTYP_PLURAL']: $Ue['BASISTYP']) . '</td><td>' . $btype . '</td><td>VA</td></tr>';
		
	return $result . '</table>';
}

function va_get_type_table_row ($name, $empty, $va_index, $source_index, $indexes, &$types, $sourceStr, $part_of_group){
	$result = '';
	
	if(count($indexes) == 0){
		$result .= '<tr><td>' . $name . '</td><td>' . $empty . '</td><td>VA</td></tr>';
	}
	else {
		$count_rest = count($indexes);
			
		//Source typing (if exists)
		if($source_index !== false){
			$star = '';
			if($part_of_group){
				$star .= '<font color="red">*</font>';
			}
			$result .= '<tr><td>' . $name . '</td><td class="atlasSourceB">' . $types[$source_index][1] . $star . '</td><td class="atlasSource">' . $sourceStr . '</td></tr>';
			$count_rest--;
			array_splice($indexes, $source_index, 1);
		}
		
		//Rest typings
		if($count_rest > 0){
			
			if($source_index === false){
				$result .= '<tr><td>' . $name . '</td>';
			}
			else {
				$result .= '<tr><td></td>';
			}
			
			if($count_rest == 1){
				$tname = $types[$indexes[0]][1];
				if($indexes[0] == $va_index){
					$tname = add_references($tname, $types[$va_index][3]);
				}
				$result .= '<td>' . $tname . '</td><td>' . $types[$indexes[0]][2] . '</td>';
			}
			else {
				$type_name_td = $va_index !== false? add_references($types[$va_index][1], $types[$va_index][3]) : $types[$indexes[0]][1];
				$result .= '<td>' . $type_name_td . '</td><td><select class="infoWindowTypeSelect">';
				if($va_index !== false){
					array_splice($indexes, $va_index, 1);
					$result .= '<option value="' . $types[$va_index][1] . '" data-tname="' . add_references($types[$va_index][1], $types[$va_index][3]) . '" selected>VA</option>';
				}
				foreach ($indexes as $index){
					$result .= '<option value="' . $types[$index][1] . '" data-tname="' . $types[$index][1] .  '">' . $types[$index][2] . '</option>';
				}
				$result .= '</select></td>';
			}
			$result .= '</tr>';
		}
	}
	return $result;
}

function add_references ($str, $ref_data){

	foreach ($ref_data as $ref){
		$data = explode('|', $ref);
		if($data[0] !== 'VA'){
			if($data[3]){
				$str .= '<a title="' . $data[0] . ': ' . $data[1] . ' ' . $data[2] . '" href="' . $data[3] . '" target="_BLANK" class="encyLink">' . substr($data[0], 0, 1) . '</a>';
			}
			else {
				$str .= '<span title="' . $data[0] . ': ' . $data[1] . ' ' . $data[2] . '" class="encyLink">' . substr($data[0], 0, 1) . '</span>';
			}
		}
	}
	return $str;
}

/*
	 * The result has the following format:
	 * [
	 * 	0 => [<sub id || 0> => 
	 * 					[
	 * 						0 => <record>,
	 * 						1 => <type table html>,
	 * 						2 => [concept id],
	 * 						3 => <source>,
	 * 						4 => <geo data>,
	 * 						5 => <community name>
	 * 					]
	 * 		]
	 * 	1 => [id => [lang => comment]]
	 * 	]
	 */
class IM_RecordInfoWindowData extends IM_ElementInfoWindowData {
	private $data;
	
	function __construct ($arr){
		parent::__construct('record');
		$this->data = array (
			'record' => $arr[0],
			'typeTable' => $arr[1], 
			'concepts' => $arr[2],
			'source' => $arr[3],
			'community' => $arr[4],
			'original' => $arr[5],
			'encoding' => $arr[6]
		);
	}
	
	protected function getTypeSpecificData (){
	 	return $this->data;
	}
}

function edit_va_data (){
	$db = IM_Initializer::$instance->database;

	$db->insert('a_karte_aenderungen', array('Aenderung' => json_encode($_POST['changes'])));
	echo $db->insert_id;
	//echo 'Just logging...';
}

function va_format_bounding_box ($wkt){
	$index = 9;
	
	$pos_space = strpos($wkt, ' ', $index);
	$first = substr($wkt, $index, $pos_space - $index);
	
	$index = $pos_space + 1;

	$pos_comma = strpos($wkt, ',', $index);
	$second = substr($wkt, $index, $pos_comma - $index);
	
	//Skip useless point:
	$index = strpos($wkt, ',', $pos_comma + 1) + 1;
	
	$pos_space = strpos($wkt, ' ', $index);
	$third = substr($wkt, $index, $pos_space - $index);
	
	$index = $pos_space + 1;
	
	$pos_comma = strpos($wkt, ',', $index);
	$fourth = substr($wkt, $index, $pos_comma - $index);
	
	return array($first, $second, $third, $fourth);
}

function va_is_hex_category ($id_cat, $type){
	$db = IM_Initializer::$instance->database;
	
	if($type == 6){
		//Accept all areas that have a hexagon grid (== entry in Polygone_Vereinfacht with epsilon < 0)
		return $db->get_var($db->prepare('
			SELECT DISTINCT Epsilon FROM Z_Geo
			WHERE Epsilon < 0 AND Id_Category = %d', $id_cat)) != NULL;
	}
	else {
		//Accept all categories that exclusively consist of points
		return $db->get_var($db->prepare("
			SELECT DISTINCT GeometryType(Geo_Data) FROM Z_Geo WHERE GeometryType(Geo_Data) != 'POINT' AND Id_Category = %d", $id_cat)) == NULL;
	}
}

function search_va_locations ($search){
	global $Ue;
	global $lang;
	
	$db = IM_Initializer::$instance->database;
	$query = 'SELECT 
            Id_Geo AS id, 
            Name AS text, 
            Category_Name AS description 
        FROM Z_Geo
        WHERE Name LIKE "%'.$search.'%" 
        GROUP BY Name 
        ORDER BY description ASC, text ASC';
	
	$names = $db->get_results($query);
	
	foreach ($names as $index => $name){
		$names[$index]->description = va_sub_translate($names[$index]->description, $Ue);
		$names[$index]->text = va_translate_extra_ling_name($names[$index]->text, $lang);
	}
	
	return ['results' => $names];
}

function get_va_location ($id){
    global $lang;
    
	$db = IM_Initializer::$instance->database;
	$query = 'SELECT 
                ST_AsText(ST_Envelope(IFNULL(Center, Geo_Data))) AS point, 
                Name as text 
            From Z_Geo WHERE Id_Geo = %f';
	
	$result = $db->get_row($db->prepare($query, $id), ARRAY_A);
	$result['text'] = va_translate_extra_ling_name($result['text'], $lang);
	
	return $result;
}

function va_ling_search ($search, $lang){
	global $Ue;
	$lang = strtoupper(substr($lang, 0, 1));
	$db = IM_Initializer::$instance->database;
	
	$query1 = "SELECT DISTINCT Id_Base_Type, Base_Type FROM z_ling WHERE Base_Type LIKE '%$search%' ORDER BY Base_Type ASC";
	$bresult = $db->get_results($query1, ARRAY_A);
	$basetypes = [];
	foreach ($bresult as $btype){
		$basetypes[] = ['id' => 'B' . $btype['Id_Base_Type'], 'text' => va_format_base_type($btype['Base_Type']), 'description' => $Ue['BASISTYP_PLURAL']];
	}
	
	$query2 = "
		SELECT DISTINCT GROUP_CONCAT(DISTINCT Id_Type ORDER BY Type ASC, Gender ASC SEPARATOR '+') AS Ids, Type, Type_Lang, POS, Affix 
		FROM z_ling 
		WHERE Source_Typing = 'VA' AND Type_Kind = 'L' AND Type LIKE '%$search%' COLLATE utf8mb4_general_ci 
		GROUP BY Type, Type_Lang, POS 
		ORDER BY Type ASC, Type_Lang ASC";
	$mresult = $db->get_results($query2, ARRAY_A);
	$morphtypes = [];
	foreach ($mresult as $mtype){
		$morphtypes[] = [
			'id' => 'L' . $mtype['Ids'], 
			'text' => va_format_lex_type($mtype['Type'], $mtype['Type_Lang'], $mtype['POS'], '', $mtype['Affix']),
			'description' => $Ue['MORPH_TYP_PLURAL']
		];
	}
	
	$query2_1 = "
		SELECT DISTINCT CONCAT('P', Id_Type) as id, Type as text, '" . $Ue['PHON_TYP_PLURAL'] . "' as description
		FROM z_ling
		WHERE Source_Typing = 'VA' AND Type_Kind = 'P' AND Type LIKE '%$search%'
		ORDER BY Type ASC, Type_Lang ASC";
	$ptypes = $db->get_results($query2_1, ARRAY_A);
	
	$query3 = "
    SELECT 
        CONCAT('C', Id_Konzept) AS id, 
        IF(Name_$lang != '', Name_$lang, Beschreibung_$lang) AS text, 
        '{$Ue['KONZEPT_PLURAL']}' AS description FROM konzepte JOIN A_Anzahl_Konzept_Belege USING (Id_Konzept)
    WHERE (Name_D LIKE '%$search%' OR Name_I LIKE '%$search%' OR Name_F LIKE '%$search%' OR Name_R LIKE '%$search%' OR Name_S LIKE '%$search%' 
	OR Beschreibung_D LIKE '%$search%' OR Beschreibung_I LIKE '%$search%' OR Beschreibung_F LIKE '%$search%' OR Beschreibung_R LIKE '%$search%' OR Beschreibung_S LIKE '%$search%') AND Relevanz 
    ORDER BY text ASC";
	$concepts = $db->get_results($query3);
	
	usort($concepts, function ($a,$b) use ($search){
		$t1 = $a->text;
		$t2 = $b->text;
		
		return va_concept_compare($t1, $t2, $search);
	});
	
	$query4 = "
    SELECT DISTINCT
        CONCAT('I', Erhebung) as id,
        Erhebung as text,
        '{$Ue['INFORMANTEN']}' as description
    FROM Informanten WHERE Erhebung LIKE '%$search%'
    ORDER BY Erhebung ASC";
	$informants = $db->get_results($query4);
	
	$query5 = "
    SELECT DISTINCT
        CONCAT(IF(GeometryType(Geo_data) = 'POLYGON' OR GeometryType(Geo_data) = 'MULTIPOLYGON', 'A', 'E'), Id_Category) as id,
        Category_Name AS text,
        IF(GeometryType(Geo_data) != 'POLYGON' AND GeometryType(Geo_data) != 'MULTIPOLYGON', '{$Ue['AUSSERSPR']}', '{$Ue['POLYGONE']}') as description
    FROM Z_Geo";
	$extra = $db->get_results($query5);
	foreach ($extra as $e){
	    $e->text = va_sub_translate($e->text, $Ue);
	}
	
	$extra = array_filter($extra, function ($e) use ($search){
		return mb_strpos(mb_strtolower($e->text), mb_strtolower($search)) !== false;
	});
	
	$query6 = "
	SELECT 
		CONCAT('SYN', Id_Syn_Map) AS id,
		Name AS text,
		'{$Ue['SYN_MAPS_MENU']}' AS description
	FROM im_syn_maps
	WHERE Name LIKE '%$search%' AND Name != 'Anonymous' AND (Released = 'Released' OR Author = '" .  wp_get_current_user() -> user_login . "')";
	$syn_maps = $db->get_results($query6);

	$query7 = 'SELECT 
            Id_Geo AS id, 
            Name AS text, 
            Category_Name AS description 
        FROM Z_Geo
        WHERE Name LIKE "%'.$search.'%" 
        GROUP BY Name 
        ORDER BY description ASC, text ASC';
	
	$locations = $db->get_results($query7);
	
	foreach ($locations as $index => $name){
		$locations[$index]->description = va_sub_translate($locations[$index]->description, $Ue);
		$locations[$index]->text = va_translate_extra_ling_name($locations[$index]->text, $lang);
		$locations[$index] ->id = "LOC".$locations[$index] ->id;
	}
	
	$names = array_merge($basetypes, $morphtypes, $ptypes,  $concepts, $informants, $extra, $syn_maps,$locations);
	
	return ['results' => $names];
}
?>