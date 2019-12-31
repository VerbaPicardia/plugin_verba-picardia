<?php

function create_va_map (){

global $lang;
global $Ue;
global $admin;
global $va_mitarbeiter;
global $va_current_db_name;

$concepts = IM_Initializer::$instance->database->get_results("
								SELECT
									a1.Id_Konzept,
									Name_$lang AS Name,
									Beschreibung_$lang AS Beschreibung,
									kk.Hauptkategorie,
									kk.Kategorie,
									Name_$lang != '' OR a1.Id_Ueberkonzept = 707 AS Anzeige,
									a1.Anzahl_Allein AS Anzahl_Belege,
									a1.Dateiname,
									GROUP_CONCAT(u.Id_Konzept) AS Unterkonzepte
								from
									A_Anzahl_Konzept_Belege a1
									JOIN Konzepte USING (Id_Konzept)
									LEFT JOIN Konzepte_Kategorien kk USING (Id_Kategorie)
									LEFT JOIN A_Anzahl_Konzept_Belege u ON a1.Id_Konzept = u.Id_Ueberkonzept
								" . ($admin? '' : ' WHERE a1.Id_Konzept NOT IN (2195, 2197)') . "
								group by a1.Id_Konzept
								order by IF(Name_$lang = '', Beschreibung_$lang, Name_$lang)", ARRAY_A);

$concepts_JS = array();
foreach ($concepts as $concept){
	$concepts_JS[$concept['Id_Konzept']] = array (
		$concept['Name'],
		$concept['Beschreibung'],
		$concept['Anzahl_Belege'],
		$concept['Unterkonzepte']? explode(',', $concept['Unterkonzepte']): [],
		$concept['Dateiname']);
}

$sql_extra = "
		SELECT Id_Category, Category_Level_1, Category_Level_2, Category_Level_3, Category_Level_4, Category_Level_5, Category_Name,
			GeometryType(Geo_data) != 'POLYGON' AND GeometryType(Geo_data) != 'MULTIPOLYGON'
		FROM Z_Geo
		GROUP BY Id_Category
		ORDER BY Category_Level_1, Category_Level_2, Category_Level_3, Category_Level_4, Category_Level_5";
$extra_cats = IM_Initializer::$instance->database->get_results($sql_extra, ARRAY_N);

$eling_JS = array();
foreach ($extra_cats as $ecat){
	$te = va_sub_translate($ecat[6], $Ue);
	$eling_JS[$ecat[0]][0] = $te;

	$tags = IM_Initializer::$instance->database->get_results("SELECT Tag, Wert, Alpenkonvention FROM A_Kategorie_Tag_Werte WHERE Id_Kategorie = "  . $ecat[0], ARRAY_N);

	if(count($tags) > 0){
		$tagList = array();
		foreach ($tags as $tag){
			if(!isset($tagList[$tag[0]])){
				$tagList[$tag[0]] = array();
			}
			array_push($tagList[$tag[0]], array('value' => $tag[1], 'ak' => $tag[2]));
		}

		$eling_JS[$ecat[0]][1] = $tagList;
	}
	else {
		$eling_JS[$ecat[0]][1] = NULL;
	}
}

$tagValues = IM_Initializer::$instance->database->get_col('SELECT DISTINCT Wert FROM Orte_Tags');

//TODO Better solutation! Don't abuse getElementName!!!
$tagNames = IM_Initializer::$instance->database->get_col('SELECT DISTINCT Tag FROM Orte_Tags');
$tagValues = array_merge($tagValues, $tagNames, array('EMPTY'));

$sourcesLists = IM_Initializer::$instance->database->get_results('SELECT Id, Quellenliste FROM A_Quellenliste', ARRAY_N);

$sourceMapping = array();
foreach ($sourcesLists as $sourceEntry){
	$sourceMapping[$sourceEntry[0]] = $sourceEntry[1];
}


$typeGenders = IM_Initializer::$instance->database->get_results("SELECT Id_morph_Typ, Genus FROM morph_Typen WHERE Quelle = 'VA'", ARRAY_N);

$qids = IM_Initializer::$instance->database->get_results('SELECT DISTINCT Id_Concept, QID FROM Z_Ling WHERE QID IS NOT NULL AND QID != 0', ARRAY_N);

wp_localize_script ('im_map_script', 'Ue', $Ue);
wp_localize_script ('im_map_script', 'Concepts', $concepts_JS);
wp_localize_script ('im_map_script', 'ELing', $eling_JS);
wp_localize_script ('im_map_script', 'TagValues', $tagValues);
wp_localize_script ('im_map_script', 'SourceMapping', $sourceMapping);
wp_localize_script ('im_map_script', 'TypeGenders', va_two_dim_to_assoc($typeGenders));
wp_localize_script ('im_map_script', 'QIDS', va_two_dim_to_assoc($qids));

?>
<style type="text/css">
.search_container input{
    background-image: url(<?php echo IM_PLUGIN_URL;?>/icons/font-awesome_4-7-0_search_256_0_ffffff_none.png) !important; <?php //TODO generalize?>
}
</style>

<div id="<?php echo im_main_div_class();?>">

<?php //im_create_map(0); ?>


			<div class = "tablecontainer">

			<div class ="upperbg"></div>
				<div id="leftTable" style="width: 280pt;">



				     		<div class="search_container">
				         		<select multiple="multiple" class="global_search"></select> <?php //TODO move to im?>
				         	</div>

					<div id="trSelectionBar" class="menu_grp active">

				      <h2 style="padding-top: 13px;" class="menu_heading" id="selection_heading">


						    <i class="fa fa-caret-down menu_caret" aria-hidden="true"></i>
								<?php echo ucfirst($Ue['KARTOGRAPHISCH']);?>


						  <div class="map_mode_selection">
						  <?php if (va_version_newer_than('va_171')){?>
							<div class="btn-group" data-toggle="buttons">
							  <label title="<?php echo ucfirst($Ue['PHY_DESC']);?>" id="phy_label" class="btn btn-secondary btn-sm active mode_switch_label">
							    <input type="radio" name="options" id="option1" autocomplete="off" checked> <?php echo ucfirst($Ue['PHY']); ?>
							  </label>
							  <label title="<?php echo ucfirst($Ue['HEX_DESC']);?>" id="hex_label" class="btn btn-secondary btn-sm mode_switch_label">
							    <input type="radio" name="options" id="option2" autocomplete="off"> <?php echo ucfirst($Ue['HEX']); ?>
							  </label>
							</div>
								<?php
								}
								echo va_get_mouseover_help($Ue['HILFE_KARTE'], $Ue, IM_Initializer::$instance->database, $lang, 34); ?>
						     </div>
							</h2>



				     <div class="menu_collapse collapse_p active">


				     		<!-- <div><i class="fas fa-search"></i>Search...</div> -->

							<h6 class="VA_Map_Subhead search_language_data search"><?php echo ucfirst($Ue['SPRACHDATEN']); ?></h6>

							<div class="va_select_container">

								<?php

								//Base types
								echo im_table_select('Z_Ling', array('Id_Base_Type'), array('Base_Type'), 'baseTypeSelect', array(
										'list_format_function' => 'va_format_base_type',
										'placeholder' => ucfirst($Ue['BASISTYP_PLURAL']),
										'width' => '90%',
										'filter' => 'Id_Base_Type IS NOT NULL',
										'sort_simplification_function' => 'va_remove_special_chars'
								));
								echo va_get_mouseover_help($Ue['HILFE_BASISTYP'], $Ue, IM_Initializer::$instance->database, $lang, 58);

								?>

							</div>

							<div class="va_select_container">

								<?php

								//Morphologic types
								echo im_table_select('Z_Ling', array('Id_Type'), array('Type', 'Type_Lang', 'POS', "'' AS Gender", 'Affix'), 'morphTypeSelect', array(
										'list_format_function' => array('va_format_lex_type', &$Ue),
										'placeholder' => ucfirst($Ue['MORPH_TYP_PLURAL']),
										'width' => '90%',
										'filter' => "Type_Kind != 'P' AND Source_Typing = 'VA'" . ($admin? '': ' AND Id_Type != 6977'),
										'sort_simplification_function' => 'va_remove_special_chars',
										'group_by' => 'Type, Type_Lang, POS',
										'group_order_by' => 'Type ASC, Gender ASC'
									));
								echo va_get_mouseover_help($Ue['HILFE_MORPH'], $Ue, IM_Initializer::$instance->database, $lang, 58);

								?>

						    </div>

								<div class="va_select_container">
									<?php


// 								echo im_table_select('Z_Ling', array('Id_Type'), array('Type'), 'phonTypeSelect', array(
// 										'placeholder' => ucfirst($Ue['PHON_TYP_PLURAL']),
// 										'width' => '90%',
// 										'filter' => "Type_Kind = 'P' AND Source_Typing = 'VA'"
// 								));
// 								echo va_get_mouseover_help($Ue['HILFE_PHON'], $Ue, IM_Initializer::$instance->database, $lang, 58);



									?>
							    </div>


								<?php
								//Concepts
								$ueKat = function ($word) use (&$Ue){
									if($word == ''){
										return $Ue['KEINE_DOMAENE'];
									}
									if(isset($Ue[$word]))
										return $Ue['DOMAENE'] . ' ' . $Ue[$word];
									return $word;
								};

								$kats = array();
								$params = array();

								foreach ($concepts as $index => $concept){
									if($concept['Anzeige']){
										$kats[$index] = array($concept['Id_Konzept'], va_translate($concept['Hauptkategorie'], $Ue), $ueKat($concept['Kategorie']));
										$hasName = $concept['Name'] != '' && $concept['Name'] != $concept['Beschreibung'];
										if($hasName){
											$kats[$index][] = $concept['Name'];
										}
										else {
											$kats[$index][] = $concept['Beschreibung'];
										}
										if($hasName || $concept['Dateiname']){
											$params[$index] = array ('class' => 'conceptTooltip');
										}
									}
								}
								uasort($kats, function ($a, $b){
									$katComp = strcmp($a[1], $b[1]);
									if($katComp == 0){
										return strcmp($a[2], $b[2]);
									}
									return $katComp;
								});

								?>

							<div class="va_select_container">

								<div style="display: inline-block; width: 90%">
								<?php
									echo im_hierarchical_select('conceptSelect', ucfirst($Ue['KONZEPT_PLURAL']), $kats, $params, array ('style' => 'width : 90%'));
								?>
								</div>




										<?php
										echo va_get_mouseover_help($Ue['HILFE_KONZEPT'], $Ue, IM_Initializer::$instance->database, $lang, 37);
										?>
							   </div>

							<h6 class="VA_Map_Subhead search_peripherie search"> <?php echo ucfirst($Ue['PERIPHERIE']); ?></h6>

							<div class="va_select_container">
							<?php

							//Informants
							$idata =[
								'placeholder' => ucfirst($Ue['INFORMANTEN']),
								'width' => '90%',
							];

							if (!$admin){
								$idata['filter'] = ' Erhebung != "TEST"';
							}

							echo im_table_select('Informanten', 'Erhebung', array('Erhebung'), 'informantSelect', $idata);
							echo va_get_mouseover_help($Ue['HILFE_INFORMANTEN'], $Ue, IM_Initializer::$instance->database, $lang, 30);

							//Extra-linguistic
// 							foreach($extra_cats as &$row){
// 								for($i = 1; $i < 6; $i++){
// 									if(isset($Ue[$row[$i]]))
// 										$row[$i] = $Ue[$row[$i]];
// 								}
// 							}

// 							$extra_cats = array_filter($extra_cats, function ($e){
// 								return $e[7] == '1' /* No polygon*/;
// 							});


								?>
						   </div>

<!-- 							<div class="va_select_container"> -->

								<div style="display: inline-block; width: 90%">
								<?php
// 									echo im_hierarchical_select('extraLingSelect', ucfirst($Ue['AUSSERSPR']), $extra_cats, array (), array ('style' => 'width : 90%'));
// 								?>
<!-- 								</div> -->
										<?php
// 										echo va_get_mouseover_help($Ue['HILFE_AUSSERSPR'], $Ue, IM_Initializer::$instance->database, $lang, 3);
// 										?>
<!-- 							 </div> -->

<!-- 							<div class="va_select_container"> -->

							<?php

// 							//Areas
// 							echo im_table_select('Z_Geo', 'Id_Category', array('Category_Name'), 'polygonSelect', array(
// 									'placeholder' => ucfirst($Ue['POLYGONE']),
// 									'width' => '90%',
// 									'list_format_function' => array('va_sub_translate', &$Ue),
// 									'filter' => "GeometryType(Geo_data) = 'POLYGON' OR GeometryType(Geo_data) = 'MULTIPOLYGON'"
// 							));

// 							echo im_table_select('Z_Geo', array('Id_Category', 'CAST(Epsilon AS SIGNED)'), array('Category_Name', 'Id_Category', 'Epsilon'), 'hexagonSelect', array(
// 									'placeholder' => ucfirst($Ue['POLYGONE']),
// 									'width' => '90%',
// 									'list_format_function' => array('va_translate_hexagon_grids', &$Ue),
// 									'filter' => 'Epsilon < 0',
// 									'custom_style_attributes' => 'display: none;'
// 							));

// 							echo va_get_mouseover_help($Ue['HILFE_FLAECHEN'], $Ue, IM_Initializer::$instance->database, $lang, 88);

// 							?>

<!-- 	 						</div>							 -->

					      </div>
					</div>

					<div class="menu_grp">

						<h2 class="menu_heading" id="legend_heading">
							<i class="fa fa-caret-right menu_caret" aria-hidden="true"></i>
							<?php echo ucfirst($Ue['LEGEND']);?>
						</h2>

						 <div class="menu_collapse" style="display: none">
							<?php im_create_legend(); ?>
						</div>

					</div>

					<div class="menu_grp">

						<h2 class="menu_heading" id="syn_heading">
						    <i class="fa fa-caret-right menu_caret" aria-hidden="true"></i>
							 <?php echo ucfirst($Ue['SYN_MAPS_MENU']);?>
						</h2>
							<div class="menu_collapse collapse_p" style="display: none;">
							<?php
							$syn_readonly = $va_current_db_name === 'va_xxx' && !$va_mitarbeiter;
							IM_Initializer::$instance->database->select('va_xxx');
							echo im_create_synoptic_map_div('90%', $syn_readonly, va_get_glossary_help(56, $Ue));
							IM_Initializer::$instance->database->select($va_current_db_name);
							?>
						   </div>

					</div>
				</div>

			     <div class="move_menu_container">
					<div class="move_menu">
					 <div class="move_menu_wave_border" id="move_menu_left"></div>
					   <div class="move_menu_center">
							<span class="active"><i class="fa fa-caret-up" aria-hidden="true"></i> 	<?php echo ucfirst($Ue['CLOSE_SIDEBAR']);?> </span>
				    		<span class="inactive" style="display: none"><i class="fa fa-caret-down" aria-hidden="true"></i> <?php echo ucfirst($Ue['OPEN_SIDEBAR']);?> </span>
				    	</div>
				     <div  class="move_menu_wave_border" id="move_menu_right"></div>
					</div>
				</div>
	</div>

</div>

<div id="VAbibDiv" style="display: none"></div>
<div id="VAstiDiv" style="display: none"></div>

<?php

im_create_filter_popup_html();
im_create_comment_popup_html();
im_create_save_map_popup_html();
im_create_loc_nav_popup_html($Ue);
im_create_lang_search_html($Ue);
im_create_ajax_nonce_field ();
im_create_debug_area();
va_create_hex_popup_html($Ue);
va_create_sql_help_text($Ue);
va_create_list_popup_html($Ue);
va_create_export_list_popup_html($Ue);
}

function va_translate_hexagon_grids ($str, $id, $epsilon, &$Ue){
	$res = va_sub_translate($str, $Ue);

	$translation_key = 'HEXGRIDA' .+ $id . '|' . intval($epsilon);

	if(isset($Ue[$translation_key])){
		$res .= ' (' . $Ue[$translation_key] . ')';
	}

	return $res;
}

function va_create_hex_popup_html (&$Ue){

	?>
	<div class="modal fade select_hex_popup" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
	  <div class="modal-dialog modal-sm">
	    <div class="modal-content">

	   <div class="modal-header hex_header" style="background: url(<?php echo IM_PLUGIN_URL; ?>icons/hex_grid.png)">
        <h5 class="modal-title"><span><?php echo $Ue['HEX_OVERLAYS']; ?></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>

	       <div class="modal-body">

    	<!--     <h5 class="hex-modal-title">Overlays:</h5>		 -->

	       <div class="btn-group-vertical hex-modal-btn-grp" role="group" aria-label="Vertical button group">

		   </div>


	      </div>

	    </div>
	  </div>
	</div>
<?php
}



function va_create_sql_help_text ($Ue){
	$res = '<b>' . $Ue['VORHANDENE_SPALTEN'] . '</b><br /><br />';
	$res .= '<table><tr><td><table>';
	$cols = IM_Initializer::$instance->database->get_results("select column_name, data_type from information_schema.`COLUMNS` where TABLE_SCHEMA = 'va_xxx' and table_name = 'z_ling'", ARRAY_A);

	$half = intval(count($cols) / 2);
	foreach ($cols as $i => $col){
		$res .= '<tr><td>' . $col['column_name'] . '</td><td>' . va_translate_col_type($col['data_type']) . '</td></tr>';

		if($i == $half){
			$res .= '</table></td><td style="padding-left: 20px;"><table>';
		}
	}
	$res .= '</table></td></tr></table>';

	echo '<div id="va_sql_help_div" style="display: none;">' . $res . '</div>';
}

function va_translate_col_type ($name){
	return $name;
}
?>