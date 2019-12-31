<?php
function va_create_tokenizer_page () {
	?>
	<script type="text/javascript">

	var loadingHTML = "<img src='<?php echo VA_PLUGIN_URL . '/images/Loading.gif';?>' />";

	jQuery(function (){
		jQuery("#stimField").val("");

		jQuery("#searchRecords").click(searchRecords);
		jQuery("#stimField").on("keypress", function (event){
			var keycode = (event.keyCode ? event.keyCode : event.which);

			if(keycode == '13'){
				searchRecords();
			}
		});

		function searchRecords (keepResult){
			var data = {
				"action" : "va",
				"namespace" : "tokenize",
				"query" : "updateTable",
				"stimulus" : jQuery("#stimField").val(),
			};
			jQuery.post(ajaxurl, data, function (response) {
				try {
					response = JSON.parse(response);
					jQuery("#atabelle").toggle(true);
					jQuery('#atabelle').html(response[0]);
					jQuery("#tokButtons").toggle(response[1] * 1 > 0);
					if (keepResult !== true){
						jQuery("#resultArea").html("");
					}
				}
				catch (e){
					jQuery("#atabelle").toggle(false);
					jQuery("#tokButtons").toggle(false);
					jQuery("#resultArea").html(response);
				}
				jQuery("#resultArea").toggle(true);
			});
		}

		jQuery("#tokenizePreview").click(function (){
			tokenize(1);
		});

		jQuery("#tokenizeRecords").click(function (){
			tokenize(0);
		});

		function tokenize (preview){
			jQuery("#resultArea").html(loadingHTML);
			jQuery("#resultArea").toggle(true);
			var data = {
					"action" : "va",
					"namespace" : "tokenize",
					"query" : "tokenize",
					"stimulus" : jQuery("#stimField").val(),
					"preview" : preview
				};
			jQuery.post(ajaxurl, data, function (response) {
				if (!response.startsWith("Error")){
					jQuery("#tokButtons").toggle(false);
					searchRecords(true);
				}
				jQuery("#resultArea").html(response);
				jQuery("#resultArea").toggle(true);
			});
		}

		jQuery("#stimField").on("change, input", function (){
			jQuery("#atabelle").toggle(false);
			jQuery("#tokButtons").toggle(false);
			jQuery("#resultArea").toggle(false);
		});
	});
	</script>

	<br /><h1>Tokenisierung</h1><br /><br />

	<input class="button button-primary" type="button" id="searchRecords" value="Äußerungen suchen"/>
	WHERE Id_Stimulus <input id="stimField" type="text" />
	<?php echo va_get_info_symbol("Muss zusammen eine gültige WHERE-Klausel ergeben, mögliche Eingaben, z.B.:\n\n= 12\nIN (1, 2, 3)\nBETWEEN 100 AND 107"); ?>

	<br />
	<br />

	<div id="atabelle" style="display: none;">
		<?php echo va_records_for_stimulus(''); ?>
	</div>

	<br />
	<br />

	<div id="tokButtons" style="display: none;">
		<input type="button" class="button button-primary" id="tokenizePreview" value="Vorschau" />
		<input type="button" class="button button-primary" id="tokenizeRecords" value="Tokenisieren" />
	</div>

	<br />
	<br />

	<div id="resultArea"></div>

	<?php
}

function va_records_for_stimulus ($id_Stimulus) {
    global $va_xxx;

    $id_Stimulus = stripslashes($id_Stimulus);

    $sql = "select count(*), (select count(*) from Aeusserungen WHERE Aeusserung != '<vacat>' AND Aeusserung != '<problem>' AND Id_Stimulus " . $id_Stimulus . ") from Aeusserungen where Tokenisiert = 0 AND Aeusserung != '<vacat>' AND Aeusserung != '<problem>'  AND Aeusserung NOT REGEXP '^<.*>$' AND Id_Stimulus " . $id_Stimulus;
    $result = $va_xxx -> get_results($sql, ARRAY_N);
    return '<table border="2">
		<tr>
			<th>Äußerungen gesamt</th>
			<th>Nicht tokenisiert</th>
		</tr>
		<tr>
			<td>' . $result[0][1] . '</td>
			<td>' . $result[0][0] . '</td>
		</tr>
	</table>';
}

function va_tokenize_for_stimulus ($id_stimulus, $preview){

	if (!current_user_can('va_tokenization')){
		//There is no protection against sql injection
		die;
	}

	global $va_xxx;

	$records = $va_xxx->get_results('
		SELECT Id_Aeusserung, Aeusserung, Klassifizierung, Aeusserungen.Bemerkung, Sprache, Stimuli.Erhebung, GROUP_CONCAT(Id_Konzept) AS Konzepte, Id_Stimulus, Id_Informant, Erfasst_Von, Erfasst_Am
		FROM Aeusserungen JOIN Stimuli USING (Id_Stimulus) JOIN Informanten USING (Id_Informant) LEFT JOIN VTBL_Aeusserung_Konzept USING (Id_Aeusserung)
		WHERE Not Tokenisiert AND Aeusserung NOT LIKE "<%>" AND Id_Stimulus ' . stripslashes($id_stimulus) .'
		GROUP BY Id_Aeusserung', ARRAY_A);

	$tokenizer = va_create_tokenizer($records[0]['Erhebung']);

	$inserts = [];
	$index = 0;

	$mtypes = [];
	$ptypes = [];


	$warnings = [];
	$errors = [];

	foreach ($records as $record){

		if ($record['Id_Stimulus'] == 97217){ //DizMT
			$record['Bemerkung'] = '';
		}

		try {
			$current_id = $record['Id_Aeusserung'];

			$res = $tokenizer->tokenize($record['Aeusserung'],
				['class' => $record['Klassifizierung'],
				'concepts' => ($record['Konzepte']? explode(',', $record['Konzepte']) : []),
				'notes' => $record['Bemerkung'],
				'lang' => $record['Sprache']
			]);

			foreach ($res['global']['warnings'] as $warning){
				if (!isset($warnings[$warning])){
					$warnings[$warning] = [];
				}
				$warnings[$warning][] = $current_id;
			}

			$index_mapping = []; //Maps "local" type ids from newly created types to "global" ones (used for either morph. or phon. types)
			$group_mapping = []; //Does the same for group ids

			foreach ($res['global']['mtypes'] as $i => $mtype){
				$it = va_type_in_array($mtype, $mtypes);

				if (!$mtype['Orth']){

					throw new Exception('Not possible to create morph. type for "' . $mtype['Beta'] . '": (' . implode(';', $res['global']['warnings']) . ')');
				}

				if ($it === false){
					$inserts[$index] = ['morph_Typen', $mtype, $current_id];
					$it = count($mtypes);
					$mtypes[] = [$index++, $mtype];
				}

				$index_mapping[$i] = $it;
			}

			foreach ($res['global']['ptypes'] as $i => $ptype){
				$it = va_type_in_array($ptype, $ptypes);
				if ($it === false){
					$inserts[$index] = ['phon_Typen', $ptype, $current_id];
					$it = count($ptypes);
					$ptypes[] = [$index++, $ptype];
				}

				$index_mapping[$i] = $it;
			}


			foreach ($res['global']['groups'] as $gi => $group){
				$concepts = $group['Konzepte'];
				unset($group['Konzepte']);

				$mtype = $group['MTyp'];
				unset($group['MTyp']);

				$ptype = $group['PTyp'];
				unset($group['PTyp']);

				$inserts[$index] = ['Tokengruppen', $group, $current_id];
				$index_group = $index++;

				$group_mapping[$gi] = $index_group;

				foreach ($concepts as $concept){
					$inserts[$index++] = ['VTBL_Tokengruppe_Konzept', ['Id_Tokengruppe' => '###ID' . $index_group . '###', 'Id_Konzept' => $concept], $current_id];
				}

				if ($mtype){
					if (!is_numeric($mtype)){
						$mtype = '###ID' . $mtypes[$index_mapping[substr($mtype, 3)]][0] . '###';
					}
					$inserts[$index++] = ['VTBL_Tokengruppe_morph_Typ',
						['Id_Tokengruppe' => '###ID' . $index_group . '###', 'Id_morph_Typ' => $mtype, 'Angelegt_Von' => 'tokenization', 'Angelegt_Am' => current_time('mysql')], $current_id];
				}

				if ($ptype){
					if (!is_numeric($ptype)){
						$ptype = '###ID' . $ptypes[$index_mapping[substr($ptype, 3)]][0] . '###';
					}
					$inserts[$index++] = ['VTBL_Tokengruppe_phon_Typ',
						['Id_Tokengruppe' => '###ID' . $index_group . '###', 'Id_phon_Typ' => $ptype, 'Angelegt_Von' => 'tokenization', 'Angelegt_Am' => current_time('mysql')], $current_id];
				}
			}

			foreach ($res['tokens'] as $token){
				$concepts = $token['Konzepte'];
				unset($token['Konzepte']);

				$mtype = $token['MTyp'];
				unset($token['MTyp']);

				$ptype = $token['PTyp'];
				unset($token['PTyp']);

				$token['Id_Aeusserung'] = $current_id;
				$token['Id_Stimulus'] = $record['Id_Stimulus'];
				$token['Id_Informant'] = $record['Id_Informant'];
				$token['Erfasst_Von'] = $record['Erfasst_Von'];
				$token['Erfasst_Am'] = $record['Erfasst_Am'];

				if ($token['Id_Tokengruppe']){
					$token['Id_Tokengruppe'] = '###ID' . $group_mapping[substr($token['Id_Tokengruppe'], 3)] . '###';
				}

				$inserts[$index] = ['Tokens', $token, $current_id];
				$index_token = $index++;

				foreach ($concepts as $concept){
					$inserts[$index++] = ['VTBL_Token_Konzept', ['Id_Token' => '###ID' . $index_token . '###', 'Id_Konzept' => $concept], $current_id];
				}

				if ($mtype){
					if (!is_numeric($mtype)){
						$mtype = '###ID' . $mtypes[$index_mapping[substr($mtype, 3)]][0] . '###';
					}
					$inserts[$index++] = ['VTBL_Token_morph_Typ',
						['Id_Token' => '###ID' . $index_token . '###', 'Id_morph_Typ' => $mtype, 'Angelegt_Von' => 'tokenization', 'Angelegt_Am' => current_time('mysql')], $current_id];
				}

				if ($ptype){
					if (!is_numeric($ptype)){
						$ptype = '###ID' . $ptypes[$index_mapping[substr($ptype, 3)]][0] . '###';
					}
					$inserts[$index++] = ['VTBL_Token_phon_Typ',
						['Id_Token' => '###ID' . $index_token . '###', 'Id_phon_Typ' => $ptype, 'Angelegt_Von' => 'tokenization', 'Angelegt_Am' => current_time('mysql')], $current_id];
				}
			}

		}

		catch (Exception $e){
			$errors[] = 'Error with record ' . $current_id . ': ' . $e->getMessage();
		}
	}

	if ($errors){
		echo implode('<br />', $errors);
	}
	else {
		if ($preview){
			if ($warnings){
				echo '<h3>Warnungen</h3>';
			}

			foreach ($warnings as $warning => $record_ids){
				echo $warning . ' (Äußerungen: ' . implode(', ', $record_ids) . ')<br />';
			}

			echo '<br /><h3>Inserts:</h3><table style="border: solid black 1px;"><tr><th>Index</th><th>Daten</th><th>Id_Aeusserung</th></tr>';

			$current_color_index = 0;
			$colors = ['AliceBlue', 'white'];
			$last_id = NULL;

			foreach ($inserts as $i => $insert){
				if ($last_id != $insert[2]){
					$last_id = $insert[2];
					$current_color_index = ($current_color_index + 1) % count($colors);
				}

				echo '<tr style="background: ' . $colors[$current_color_index] . ';"><td id="index' . $i . '">' . $i . '</td><td>' . $insert[0] . ' -> ' . va_tok_id_links(va_format_long_json($insert[1], 220)) . '</td><td>' . $insert[2] . '</td></tr>';
			}

			echo '</table>';
		}
		else {

			$va_xxx->query('START TRANSACTION');

			$created_ids = [];
			$ca_id = $inserts[0][2];


			foreach ($inserts as $i => $insert){
				foreach ($insert[1] as $key => $val){
					if (preg_match('/^###ID.*###$/', $val)){
						$insert[1][$key] = $created_ids[substr($val, 5, -3)];
					}
				}

				if($va_xxx->insert($insert[0], $insert[1]) === false){
					echo 'Error: ' . $va_xxx->last_error;
					$va_xxx->query('ROLLBACK');
					die;
				}
				$created_ids[$i] = $va_xxx->insert_id;

				if ($i == count($inserts) - 1 || $ca_id != $inserts[$i + 1][2]){
					$ca_id = $inserts[$i + 1][2];
					$va_xxx->update('Aeusserungen', ['Tokenisiert' => 1], ['Id_Aeusserung' => $insert[2]]);
				}
			}

			$va_xxx->query('COMMIT');
			echo count($inserts) . ' Datensätze eingefügt!';
		}
	}
}

function va_format_long_json ($arr, $width_border){
	$rest = json_encode($arr, JSON_UNESCAPED_UNICODE);
	$res = '';

	while (mb_strlen($rest) > $width_border){
		$index_cut = mb_strpos($rest, ',"', $width_border);

		if ($index_cut === false){
			break;
		}

		$res .= htmlentities(mb_substr($rest, 0, $index_cut + 1)) . '<br />';
		$rest = mb_substr($rest, $index_cut + 1);
	}

	$res = stripslashes($res);
	$res .= htmlentities($rest);
	return $res;
}

function va_tok_id_links ($str){
	return preg_replace('/###ID([^#]*)###/', '<a href="#index$1">aus $1</a>', $str);
}

function va_type_in_array ($type, $arr){
	foreach ($arr as $index => $entry){
		$identical = true;
		foreach ($type as $key => $val){
			if ($entry[1][$key] !== $val){
				$identical = false;
				break;
			}
		}

		if ($identical)
			return $index;
	}

	return false;
}

function va_tokenization_info_for_stimulus ($id_stimulus) {
	if (!current_user_can('va_tokenization')){
		//There is no protection against sql injection
		die;
	}

	global $va_xxx;

	$id_stimulus = stripslashes($id_stimulus);

	$sql = '
		SELECT ifnull(sum(IF (Tokenisiert = 0, 1, 0)), 0), count(*), count(DISTINCT Erhebung), sum(IF(Erhebung = "CROWD" AND Not Gesperrt, 1, 0))
		FROM Aeusserungen JOIN Stimuli USING (Id_Stimulus)
		WHERE Aeusserung NOT LIKE "<%>" AND Id_Stimulus ' . $id_stimulus;
	$result = $va_xxx -> get_row($sql, ARRAY_N);

	if ($result[2] > 1){
		echo 'Belege aus unterschiedlichen Atlanten können nicht gleichzeitig tokenisiert werden!';
		return;
	}

	if ($result[3] > 0){
		echo 'Crowd-Belege können erst tokenisiert werden, wenn sie gesperrt sind!';
		return;
	}

	if ($result[0] > 2000){
		echo 'Es können nicht mehr als 2000 Äußerungen gleichzeitg tokenisiert werden!';
		return;
	}

	$table = '<table border="2">
		<tr>
			<th>Äußerungen gesamt</th>
			<th>Nicht tokenisiert</th>
		</tr>
		<tr>
			<td>' . $result[1] . '</td>
			<td>' . $result[0] . '</td>
		</tr>
	</table>';

	return json_encode([$table, $result[0]]);
}

function va_create_tokenizer ($source){

	if($source == 'ALD-I'){
		$sourceUsed = 'ALD-II';
	}
	else if($source == 'SDSFB'){
		$sourceUsed = 'SDS';
	}
	else {
		$sourceUsed = $source;
	}

	global $va_xxx;
	$beta = $va_xxx->get_var($va_xxx->prepare('SELECT VA_Beta FROM Bibliographie WHERE Abkuerzung = %s', $sourceUsed));

	if ($beta){
		//Use all space from the beta code definition
		$spaceTypes = $va_xxx->get_col('SELECT Beta FROM Transkriptionsregeln WHERE Typ = "Leerzeichen"');
	}
	else {
		//Use source specific space defintion from codepage
		$spaceTypes = $va_xxx->get_col("SELECT Beta FROM Codepage_IPA WHERE Erhebung = '$sourceUsed' AND Art = 'Trennzeichen'");
		if(!$spaceTypes){
			$spaceTypes = [' '];
		}
	}

	$tokenizer = new Tokenizer([';', ',', $spaceTypes]);
	$articlesDB = $va_xxx->get_results('SELECT Artikel, Genus, Sprache FROM Artikel', ARRAY_N);
	$articles = [];
	foreach ($articlesDB as $article){
		$articles[$article[0]] = [$article[1], $article[2]];
	}
	$tokenizer->addData('articles', $articles);
	$tokenizer->addData('schars', $va_xxx->get_col('SELECT Zeichen FROM Sonderzeichen'));
	$tokenizer->addData('source', $source);

	$parser = false;
	try {
		$parser = new VA_BetaParser($sourceUsed);
	}
	catch (Exception $e){}//Not needed for unicode sources

	$tokenizer->addData('beta_parser', $parser);

	$tokenizer->addPreProcessFunction('va_add_space_after_special_chars');

	$tokenizer->addEscapeRegex('/\\\\\\\\([;,])/', '$1');
	$tokenizer->addEscapeRegex('/\\\\\\\\(.)/');

	switch ($source){
		case 'CROWD':
			$tokenizer->addReplacementString('(', '<');
			$tokenizer->addReplacementRegex('/(?<!;-)\)/', '>');
			break;

		case 'DizMT':
			$tokenizer->addReplacementString('###', ';');
			$tokenizer->addReplacementString('/', ';');
			break;
	}

	if($source == 'CROWD' || $va_xxx->get_var($va_xxx->prepare('SELECT VA_Beta FROM Bibliographie WHERE Abkuerzung = %s', $source))){
		$tokenizer->addReplacementRegex('/\s+</', '<');
		$tokenizer->addReplacementRegex('/>\s+/', '>');

		$parser = va_get_general_beta_parser($sourceUsed);

		$tokenizer->addCopyRegex('/<.*>/U', 'notes', ' ', function ($str, &$tokenizer) use (&$parser){
			$res = substr($str, 1, strlen($str) - 2);
			$res = preg_replace_callback('/#1([^#]*)##/', function ($match) use (&$parser, &$tokenizer){
				$res = $parser->convert_to_original($match[1]);
				if (!$res['string'])
					$tokenizer->error('Beta code in comment could not be parsed: ' . $match[1]);
					return $res['string'];
			}, $res);

				return $res;
		});
	}

	$tokenizer->addPostProcessFunction('va_tokenize_to_db_cols');
	$tokenizer->addPostProcessFunction('va_tokenize_split_double_genders');
	$tokenizer->addPostProcessFunction('va_tokenize_handle_groups_and_concepts');
	$tokenizer->addPostProcessFunction('va_tokenize_handle_source_types');
	$tokenizer->addPostProcessFunction('va_add_original_and_ipa');

	return $tokenizer;
}

function va_add_space_after_special_chars ($record, $extra_data){
	$index = 0;
	while (mb_substr($record, $index, 2) == '\\\\'){
		$index += 3;
	}

	if ($index > 0 && mb_substr($record, $index, 1) !== ' '){
		$record = mb_substr($record, 0, $index) . ' ' . mb_substr($record, $index);
	}

	return $record;
}

function va_add_original_and_ipa ($tokenizer, $tokens, $global, $extraData){
	$parser = $tokenizer->getData('beta_parser');

	$isConcept = function ($token, $concept){
		return isset($token['Konzepte']) && count($token['Konzepte']) == 1 && $token['Konzepte'][0] == $concept;
	};

	foreach ($tokens as &$token){
		if ($token['Token'] && $parser){ //TODO add more generic support for special concepts
			list($chars, $valid) = $parser->split_chars($token['Token']);
			if(!$valid){
				$tokenizer->error('Record not valid: ' . $token['Token'] . ' -> ' . $chars);
			}

			if (!$isConcept($token, 779)){
				$res = $parser->convert_to_ipa($chars);
				if($res['string']){
					$token['IPA'] = $res['string'];
				}
				else {
					$token['IPA'] = '';
					foreach ($res['output'] as $missing){
						if (!in_array($missing[1], $global['warnings'])){
							$global['warnings'][] = $missing[1];
						}
					}
				}
			}
			else {
				$token['IPA'] = '';
			}

			$res = $parser->convert_to_original($chars);
			if($res['string']){
				$token['Original'] = $res['string'];
			}
			else {
				$token['Original'] = '';

				if($res['output'][0][0] != 'error'){ //Ignore errors, cause some sources cannot or doesn't need to be translated
					foreach ($res['output'] as $missing){
						if (!in_array($missing[1], $global['warnings'])){
							$global['warnings'][] = $missing[1];
						}
					}
				}
			}
		}
		else {
			$token['IPA'] = '';
			$token['Original'] = '';
		}
	}

	return [$tokens, $global];
}

function va_tokenize_handle_groups_and_concepts ($tokenizer, $tokens, $global, $extraData){

	if(empty($extraData['concepts'])){
		throw new TokenizerException('No concepts given!');
	}

	$global['groups'] = [];

	$articles = $tokenizer->getData('articles');
	$schars = $tokenizer->getData('schars');

	//Split into groups
	$groups = [];
	$current_index = 0;
	foreach ($tokens as $token){
		if($token['Ebene_3'] == 1){
			$current_index++;
			$groups[$current_index] = [$token];
		}
		else {
			$groups[$current_index][] = $token;
		}
	}

	$result = [];

	$isConcept = function ($token, $concept){
		return isset($token['Konzepte']) && count($token['Konzepte']) == 1 && $token['Konzepte'][0] == $concept;
	};

	//Handle groups
	foreach ($groups as &$group){
		$len = count($group);

		if($len == 1){ //One Token
			$group[0]['Id_Tokengruppe'] = NULL;
			if (in_array($token['Token'], $schars)){
				$group[0]['Konzepte'] = [779];
			}
			else {
				$group[0]['Konzepte'] = $extraData['concepts'];
			}
		}
		else {
			$group_gender_from_article = '';
			//Mark articles and special characters
			foreach ($group as $index => $token){
				if(array_key_exists($token['Token'], $articles) && ($articles[$token['Token']][1] == '' || strpos($extraData['lang'], $articles[$token['Token']][1]) !== false)){
					$group[$index]['Konzepte'] = [699];
					$group[$index]['Genus'] = $articles[$token['Token']][0];

					if($index == 0 || ($index == 1 && $isConcept($group[$index-1], 779))){
						$group_gender_from_article = $articles[$token['Token']][0];
					}
				}
				else if (in_array($token['Token'], $schars)){
					$group[$index]['Konzepte'] = [779];
				}
			}

			//Article + token => no group
			if($len == 2 && $isConcept($group[0], 699)){
				$group[0]['Id_Tokengruppe'] = NULL;
				$group[1]['Id_Tokengruppe'] = NULL;
				$group[1]['Konzepte'] = $extraData['concepts'];

				if($group[1]['Genus'] == ''){
					$group[1]['Genus'] = $group_gender_from_article;
				}
			}
			//special char + token => no group
			else if ($len == 2 && $isConcept($group[0], 779)){
				$group[0]['Id_Tokengruppe'] = NULL;
				$group[1]['Id_Tokengruppe'] = NULL;
				$group[1]['Konzepte'] = $extraData['concepts'];
			}
			//special char + token + special char => no group
			else if ($len == 3 && $isConcept($group[0], 779) && $isConcept($group[2], 779)){
				$group[0]['Id_Tokengruppe'] = NULL;
				$group[1]['Id_Tokengruppe'] = NULL;
				$group[2]['Id_Tokengruppe'] = NULL;
				$group[1]['Konzepte'] = $extraData['concepts'];

				//Move notes to "real" token
				$group[1]['Bemerkung'] = $group[2]['Bemerkung'];
				$group[2]['Bemerkung'] = '';
			}
			//Article + token + special char => no group
			else if ($len == 3 && $isConcept($group[0], 699) && $isConcept($group[2], 779)){
				$group[0]['Id_Tokengruppe'] = NULL;
				$group[1]['Id_Tokengruppe'] = NULL;
				$group[1]['Konzepte'] = $extraData['concepts'];
				$group[2]['Id_Tokengruppe'] = NULL;

				if($group[1]['Genus'] == ''){
					$group[1]['Genus'] = $group_gender_from_article;
				}

				//Move notes to "real" token
				$group[1]['Bemerkung'] = $group[2]['Bemerkung'];
				$group[2]['Bemerkung'] = '';
			}
			//Special char + article + token => no group
			else if ($len == 3 && $isConcept($group[1], 699) && $isConcept($group[0], 779)){
				$group[0]['Id_Tokengruppe'] = NULL;
				$group[1]['Id_Tokengruppe'] = NULL;
				$group[2]['Konzepte'] = $extraData['concepts'];
				$group[2]['Id_Tokengruppe'] = NULL;

				if($group[2]['Genus'] == ''){
					$group[2]['Genus'] = $group_gender_from_article;
				}
			}
			//Group
			else {
				$indexGroup = count($global['groups']);
				$group_gender = '';
				$group_notes = $group[$len - 1]['Bemerkung'];
				$group[$len - 1]['Bemerkung'] = '';

				if($group[$len - 1]['Genus'] == ''){
					$group_gender = $group_gender_from_article;
				}
				else {
					$group_gender = $group[$len - 1]['Genus'];
				}

				$global['groups'][] = ['Genus' => $group_gender, 'Bemerkung' => $group_notes, 'Konzepte' => $extraData['concepts'], 'MTyp' => NULL, 'PTyp' => NULL];

				foreach ($group as $index => $token){
					$group[$index]['Id_Tokengruppe'] = 'NEW' . $indexGroup;
					if(!array_key_exists('Konzepte', $group[$index])){
						$group[$index]['Konzepte'] = [];
					}
					if(!$isConcept($group[$index], 699)){
						$group[$index]['Genus'] = '';
					}
					$group[$index]['Bemerkung'] = '';
				}
			}
		}

		foreach ($group as $token){
			$result[] = $token;
		}
	}

	return [$result, $global];
}

function va_tokenize_to_db_cols ($tokenizer, $tokens, $global, $extraData){
	$result = [];

	foreach ($tokens as $token){
		$newToken = [];

		$newToken['Token'] = $token['token'];

		//Set spaces for token groups
		if($token['delimiter'] == ';' || $token['delimiter'] == ',' || $token['delimiter'] === NULL){
			$newToken['Trennzeichen'] = NULL;
			$newToken['Trennzeichen_Original'] = NULL;
			$newToken['Trennzeichen_IPA'] = NULL;
		}
		else {
			$newToken['Trennzeichen'] = $token['delimiter'];

			$parser = $tokenizer->getData('beta_parser');

			if($parser){
				$space_ipa = $parser->convert_space_to_ipa($token['delimiter']);
				if($space_ipa !== false){
					$newToken['Trennzeichen_IPA'] = $space_ipa;
				}
				else {
					$newToken['Trennzeichen_IPA'] = '';
				}

				$space_org = $parser->convert_space_to_original($token['delimiter']);
				if($space_org !== false){
					$newToken['Trennzeichen_Original'] = $space_org;
				}
				else {
					$newToken['Trennzeichen_Original'] = NULL;
				}
			}
			else {
				$newToken['Trennzeichen_IPA'] = NULL;
				$newToken['Trennzeichen_Original'] = $newToken['Trennzeichen'];
			}
		}

		//Set token indexes
		foreach ($token['indexes'] as $index => $num){
			$newToken['Ebene_' . ($index + 1)] = $num + 1;
		}

		//Set notes
		$notesList = [];
		if($extraData['notes'] && $newToken['Trennzeichen'] === NULL){
			$notesList[] = $extraData['notes'];
		}
		if(isset($token['cfields']['notes'])){
			$notesList[] = $token['cfields']['notes'];
		}
		$newToken['Bemerkung'] = implode(' ', $notesList);

		$result[] = $newToken;
	}
	return [$result, $global];
}

function va_tokenize_handle_source_types ($tokenizer, $tokens, $global, $extraData){
	global $va_xxx;

	$global['mtypes'] = [];
	$global['ptypes'] = [];

	$currentGroupTypesBeta = [];
	$currentGroupTypesOrg = [];
	$indexGroup = 0;

	if($extraData['class'] != 'B'){
		$parser = $tokenizer->getData('beta_parser');

		foreach ($tokens as $index => &$token){
			$gender = $token['Genus'];
			if($parser){
				$parsed = $parser->convert_to_original($token['Token'], 'UPPERCASE');
			}
			else {
				//Already in unicode => In Token + Original
				$parsed = ['string' => $token['Token']];
			}

			if(!$parsed['string']){
				if($parsed['output'][0][0] == 'error'){
					$tokenizer->error($parsed['output'][0][1]);
				}
				else {
					foreach ($parsed['output'] as $warning){
						if(!in_array($warning[1], $global['warnings'])){
							$global['warnings'][] = $warning[1];
						}
					}
				}
			}
			else {
				$containsHtml = $parsed['string'] != strip_tags($parsed['string']);
			}

			if($extraData['class'] == 'M'){
				$type_id = $va_xxx->get_var($va_xxx->prepare('SELECT Id_morph_Typ FROM morph_Typen WHERE Beta = %s AND Genus = %s AND Quelle = %s', $token['Token'], $gender, $tokenizer->getData('source')));
				if($type_id){
					$token['MTyp'] = intval($type_id);
				}
				else {
					$token['MTyp'] = 'NEW' . count($global['mtypes']);
					$global['mtypes'][] = ['Beta' => $token['Token'], 'Orth' => ($parsed['string']?: ''), 'Genus' => $gender, 'Quelle' => $tokenizer->getData('source')];
				}
				$token['PTyp'] = NULL;
			}
			else {
				$type_id = $va_xxx->get_var($va_xxx->prepare('SELECT Id_phon_Typ FROM phon_Typen WHERE Beta = %s AND Quelle = %s', $token['Token'], $tokenizer->getData('source')));
				if($type_id){
					$token['PTyp'] = intval($type_id);
				}
				else {
					$token['PTyp'] = 'NEW' . count($global['ptypes']);
					$global['ptypes'][] = ['Beta' => $token['Token'], 'Original' => ($parsed['string']?: ''), 'Quelle' => $tokenizer->getData('source')];
				}
				$token['MTyp'] = NULL;
			}

			$currentGroupTypesBeta[] = $token['Token'] . $token['Trennzeichen'];
			if ($token['Trennzeichen_Original'] !== '' && $parsed['string']){
				if ($currentGroupTypesOrg !== NULL){
					$currentGroupTypesOrg[] = $parsed['string'] . $token['Trennzeichen_Original'];
				}
			}
			else {
				$currentGroupTypesOrg = NULL;
			}

			//Last token of group
			if($index == count($tokens) - 1 || $tokens[$index + 1]['Ebene_3'] == 1){
				if($token['Id_Tokengruppe'] !== NULL){
					$betaGroup = implode('', $currentGroupTypesBeta);
					$group = &$global['groups'][$indexGroup];
					$groupGender = $group['Genus'];

					if($extraData['class'] == 'M'){
						$gtype_id = $va_xxx->get_var($va_xxx->prepare('SELECT Id_morph_Typ FROM morph_Typen WHERE Beta = %s AND Genus = %s AND Quelle = %s', $betaGroup, $groupGender, $tokenizer->getData('source')));
						if ($gtype_id){
							$group['MTyp'] = $gtype_id;
						}
						else {
							$group['MTyp'] = 'NEW' . count($global['mtypes']);
							$global['mtypes'][] = [
								'Beta' => $betaGroup,
								'Orth' => ($currentGroupTypesOrg? implode('', $currentGroupTypesOrg): ''),
								'Genus' => $groupGender,
								'Quelle' => $tokenizer->getData('source')];
						}
					}
					else {
						$gtype_id = $va_xxx->get_var($va_xxx->prepare('SELECT Id_phon_Typ FROM phon_Typen WHERE Beta = %s AND Quelle = %s', $betaGroup,$tokenizer->getData('source')));
						if ($gtype_id){
							$group['PTyp'] = intval($gtype_id);
						}
						else {
							$group['PTyp'] = 'NEW' . count($global['ptypes']);
							$global['ptypes'][] = [
								'Beta' => $betaGroup,
								'Original' => ($currentGroupTypesOrg? implode('', $currentGroupTypesOrg): ''),
								'Quelle' => $tokenizer->getData('source')];
						}
					}

					$addGroup = $tokenizer->getData('source') . '-Typ "' . ($currentGroupTypesOrg? implode('', $currentGroupTypesOrg): $betaGroup) . '"';
					$group['Bemerkung'] = ($group['Bemerkung']? $group['Bemerkung'] . ' ' . $addGroup: $addGroup);
					$indexGroup++;
				}
				$currentGroupTypesBeta = [];
				$currentGroupTypesOrg = [];
			}

			$add = $tokenizer->getData('source') . '-Typ "' . (($parsed['string'] && !$containsHtml) ? $parsed['string'] : $token['Token']) . '"';
			$token['Token'] = '';
			$token['Bemerkung'] = ($token['Bemerkung']? $token['Bemerkung'] . ' ' . $add: $add);
		}
	}
	else {
		foreach ($tokens as &$token){
			$token['MTyp'] = NULL;
			$token['PTyp'] = NULL;
		}
	}

	return [$tokens, $global];
}

function va_tokenize_split_double_genders ($tokenizer, $tokens, $global, $extraData){
	$result = [];

	//Duplicate tokens with multiple gender information
	$currentGroup = [];

	foreach ($tokens as $index => $token){
		//Last token in group
		if($index == count($tokens) - 1 || $tokens[$index + 1]['Ebene_3'] == 1){

			if(isset($token['Bemerkung'])){
				$genderRegex = '/(?<=^|[ .,;])[MFNmfn](?=$|[ .,;])/';
				$notes = $token['Bemerkung'];
				preg_match_all($genderRegex, $notes, $matches, PREG_OFFSET_CAPTURE);

				if(count($matches[0]) > 0){
					$genderStrs = [];
					$offset = 0;
					foreach ($matches[0] as $match){
						$start = $match[1] - $offset;
						$len = ($start == strlen($notes) - 1 || $notes[$start + 1] != '.'? 1: 2);
						$genderStr = substr($notes, $start, $len);
						if(!in_array(strtolower($genderStr), array_map(function ($arr) {return strtolower($arr[0]);}, $genderStrs))){
							$genderStrs[] = [$genderStr, $start];
							$notes = substr($notes, 0, $start) . substr($notes, $start + $len);
							$offset += $len;
						}
					}


					foreach ($genderStrs as $genderStr){
						foreach ($currentGroup as $gtoken){
							$result[] = $gtoken;
						}
						$newToken = $token;
						$newToken['Bemerkung'] = trim(substr($notes, 0, $genderStr[1]) . $genderStr[0] . substr($notes, $genderStr[1]));
						$newToken['Genus'] = strtolower($genderStr[0][0]);
						$result[] = $newToken;
					}
					$currentGroup = [];
					continue;
				}
			}

			$token['Genus'] = '';
			foreach ($currentGroup as $gtoken){
				$result[] = $gtoken;
			}
			$result[] = $token;
			$currentGroup = [];
		}
		else {
			$token['Genus'] = '';
			$currentGroup[] = $token;
		}
	}
	return [$result, $global];
}