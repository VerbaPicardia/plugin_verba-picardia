<?php
function va_ajax_transcription (&$db){
	switch ($_REQUEST['query']){
		case 'update_informant':
			if(!current_user_can('va_transcription_tool_write'))
				break;
			
			echo va_transcription_update_informant($db, $_POST['id_stimulus'], $_POST['mode'], $_POST['region']);
		break;
		
		case 'update_grammar':
			$parser = new BetaParser($_POST['atlas']);
			echo json_encode([$parser->build_js_grammar_string(), ($parser->build_js_grammar_string('UPPERCASE'))]);
			break;
		
		case 'get_map_list':
			$search = '%' . $db->esc_like($_REQUEST['search']) . '%';
			
			$sql = $db->prepare('SELECT Id_Stimulus, Erhebung, Karte, Nummer, left(Stimulus,50) as Stimulus
					FROM Stimuli
					WHERE Erhebung = %s
					AND (LPAD(Karte, 4, "0") LIKE %s OR left(Stimulus,50) LIKE %s)
					ORDER BY special_cast(karte)', $_REQUEST['atlas'], $search, $search);
			
			$scans = va_transcription_list_scan_dir($_REQUEST['atlas']);
			
			$result = $db->get_results($sql, ARRAY_A);
			$options = [];
			foreach($result as $row) {
				if(isset($scans[$row['Karte']])) {
					$scan = $scans[$row['Karte']];
					$backgroundcolor="#80FF80";
				}
				else {
					$backgroundcolor="#fe7266";
				}
				$nameKarte = $row['Erhebung'] . '#' . str_pad($row['Karte'], 4, '0', STR_PAD_LEFT) . '_' . $row['Nummer'] . ' (' . $row['Stimulus'] . ')';
				$options[] = ['id' => $row['Id_Stimulus'] .  ($scan? '|' . $scan : ''), 'text' => $nameKarte, 'color' => $backgroundcolor];
				//$options .= "<option value=\"". ($value == 'None'? 'None' : $value . '|' . $scan) . "\" style=\"background-color:".$backgroundcolor."\">" .  $nameKarte . "</option>\n";
			}
			echo json_encode(['results' => $options]);
		break;
		
		case 'get_new_row':
			echo va_transcription_get_table_row($db, $_POST['index']);
		break;
	}
}

function va_transcription_list_scan_dir($atlas) {

	$atlas = remove_accents($atlas);
	$scan_dir = get_home_path() . 'dokumente/scans/' . $atlas . '/';
	
	if ($handle = opendir($scan_dir)) {
		while (false !== ($file = readdir($handle))) {

			if ($file != "." && $file != ".." && mb_strpos($file, $atlas . '#') === 0) {
				$pos_hash = mb_strpos($file, '#');
				$pos_dot = mb_strpos($file, '.pdf');
				$map = mb_substr($file, $pos_hash + 1, $pos_dot - $pos_hash - 1); 

				if(mb_strpos($map, '-') !== false){
					$numbers = explode('-', $map);
					if(ctype_digit($numbers[0]) && ctype_digit($numbers[1])){
						$start = (int) $numbers[0];
						$end = (int) $numbers[1];
						for ($i = $start; $i <= $end; $i++){
							$listing[$i] = $file;
						}
					}
					else {
						$listing[$map] = $file;
					}
				}
				else {
					$listing[$map] = $file;
				}
			}
			
		}
		closedir($handle);
	}

	return $listing;
}

function va_transcription_update_informant (&$db, $id_stimulus, $mode, $region){
	global $admin;
	
	if($mode == 'first'){
		$modusWhere = 'a.Id_Aeusserung is null';
	}
	else if ($mode == 'correct')
		$modusWhere = 'a.Id_Aeusserung is not null';
	else {
		$modusWhere = "a.Aeusserung = '<problem>'";
	}
	
	$sql = $db->prepare("
	SELECT s.Erhebung, s.Karte, s.Nummer, s.Stimulus, i.Nummer as Informant_nummer, i.ortsname, s.Id_Stimulus, i.Id_Informant, a.Aeusserung, a.Id_Aeusserung, a.Klassifizierung, a.Erfasst_Von
	FROM `stimuli` s 
		join informanten i using (Erhebung) 
		left join aeusserungen a using (Id_Stimulus, Id_Informant)  
	WHERE 
		i.Alpenkonvention
		and Id_Stimulus = %d
		and $modusWhere
		and i.Nummer like %s
	ORDER BY i.Position asc, Erfasst_am asc"
	, $id_stimulus, $region);

	$statements = $db->get_results($sql, ARRAY_A);
	
	//Use only statements with the first selected informant id
	$first_id = $statements[0]['Id_Informant'];
	foreach($statements as $index => $row){
		if($row['Id_Informant'] != $first_id){
			$break_index = $index;
			break;
		}
	}
	if($break_index)
		$results = array_slice($statements, 0, $break_index);
	else
		$results = $statements;

	 if($results[0]["Id_Stimulus"] && $results[0]["Id_Informant"]) {
		foreach ($results as $index => $row){
			if($mode == 'first' || $mode == 'extra' || $row['Aeusserung'] == '<vacat>' || $row['Aeusserung'] == '<problem>'){
				//Nur häufigstes Konzept
				$sql_concept = "SELECT Id_Konzept FROM Aeusserungen JOIN vtbl_aeusserung_konzept USING(Id_Aeusserung) WHERE Id_Stimulus = " . $row["Id_Stimulus"] . " GROUP BY Id_Konzept ORDER BY count(*) DESC LIMIT 1";
			}
			else {
				$sql_concept = "SELECT Id_Konzept FROM VTBL_Aeusserung_Konzept JOIN Aeusserungen USING(Id_Aeusserung) WHERE Id_Aeusserung = '" .$row["Id_Aeusserung"] . "'";
			}
			$conceptIds = $db->get_col($sql_concept);
			$results[$index]['Konzept_Ids'] = $conceptIds;
			
			$results[$index]['readonly'] = $mode == 'correct' && wp_get_current_user()->user_login !== $row['Erfasst_Von'] && $row['Erfasst_Von'] != '';
			ob_start();
			va_transcription_get_table_row($db, $index, $mode == 'correct'? $row['Erfasst_Von'] : '', $results[$index]['readonly']);
			$results[$index]['html'] = ob_get_clean();
		}
		
		return json_encode($results);
	}
	
	$informant_exists = $db->get_var($db->prepare('SELECT Id_Informant FROM Informanten i JOIN Stimuli USING (Erhebung) WHERE i.Nummer like %s AND Id_Stimulus = %d', $region, $id_stimulus));
	if($informant_exists){
		if($mode == 'first'){
			if($region == '%'){
				return va_transcription_error_string(__('Everything transcribed!', 'verba-alpina'));
			}
			else {
				return va_transcription_error_string(__('Already transcribed!', 'verba-alpina'));
			}
		}
		else if($mode == 'correct'){
			return va_transcription_error_string(__('No transcription existent!', 'verba-alpina'));
		}
		else{
			return va_transcription_error_string(__('No more problems!', 'verba-alpina'));
		}
	}	
	else {
		return va_transcription_error_string(__('Informant number(s) not valid!', 'verba-alpina'));
	}
}

function va_transcription_error_string ($str){
	echo '<br><br><div style="color: red; font-size: 100%; font-style: bold;">' . $str . '</div><br>';
}

function va_transcription_get_table_row (&$db, $index, $author = '', $readonly = false){

	?>
<tr id="inputRow<?php echo $index; ?>" data-index="<?php echo $index; ?>">
	<td>
		<span class="spanNumber">
			<?php echo $index + 1;?>.) 
		</span>
	</td>
	
	<td>
		<input class="inputStatement" type="text" style="width: calc(60% - 8px)" />
		<span class="previewStatement" style="width: calc(40% - 8px); vertical-align: middle; line-height : 2; display:inline-block; text-overflow : ellipsis; overflow-x:hidden !important;"></span>
	</td>
	
	<td>
		<select class="classification">
			<option value="B"><?php _e('record', 'verba-alpina');?></option>
			<option value="P"><?php _e('phon. type', 'verba-alpina');?></option>
			<option value="M"><?php _e('morph. type', 'verba-alpina');?></option>
		</select>
	</td>

	<!--<td>
		<select class="statement_pos">
			<?php 
			$options = $enum_list = im_get_enum_values_list('Aeusserungen', 'POS', 'va_xxx');
			foreach ($options as $option){
				echo "<option value='$option'>$option</option>";
			}
			?>
		</select>
	</td>
	
	<td>
		<select class="statement_gender">
			<?php 
			$options = $enum_list = im_get_enum_values_list('Aeusserungen', 'Genus', 'va_xxx');
			foreach ($options as $option){
				echo "<option value='$option'>$option</option>";
			}
			?>
		</select>
	</td>
	
	<td>
		<select class="statement_person">
			<?php 
			$options = $enum_list = im_get_enum_values_list('Aeusserungen', 'Person', 'va_xxx');
			foreach ($options as $option){
				echo "<option value='$option'>$option</option>";
			}
			?>
		</select>
	</td>
	
	<td>
		<select class="statement_tense">
			<?php 
			$options = $enum_list = im_get_enum_values_list('Aeusserungen', 'Tempus', 'va_xxx');
			foreach ($options as $option){
				echo "<option value='$option'>$option</option>";
			}
			?>
		</select>
	</td>
	
	<td>
		<select class="statement_mode">
			<?php 
			$options = $enum_list = im_get_enum_values_list('Aeusserungen', 'Modus', 'va_xxx');
			foreach ($options as $option){
				echo "<option value='$option'>$option</option>";
			}
			?>
		</select>
	</td>
	
	<td>
		<select class="statement_number">
			<?php 
			$options = $enum_list = im_get_enum_values_list('Aeusserungen', 'Numerus', 'va_xxx');
			foreach ($options as $option){
				echo "<option value='$option'>$option</option>";
			}
			?>
		</select>
	</td>-->
	
	<td>
		<select class="conceptList" data-placeholder="<?php _e('Choose Concept(s)', 'verba-alpina'); ?>" multiple style="width: 95%"></select>
		<img  style="vertical-align: middle;" src="<?php echo VA_PLUGIN_URL . '/images/Help.png';?>" id="helpIconConcepts" class="helpIcon" />
	</td>
	
	<td>
		<span class="authorSpan">
		<?php
			if($author){
				echo '<b>Erfasst&nbsp;von:&nbsp;</b>' . $author;
			}
			?>
		</span>
	</td>
	
	<td>
		<span class="deleteSpan">
			<?php 
			if($index > 0 && !$readonly){
				echo '<a class="remover" href="#">(' . __('Remove&nbsp;row', 'verba-alpina') . ')</a>'; 
			}
			?>
		</span>
	</td>
</tr><?php

}
?>