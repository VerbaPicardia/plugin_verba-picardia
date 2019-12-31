<?php

use WouterJ\Peg\Definition;

//Action Handler
add_action('wp_ajax_token_ops', 'token_ops'); //TODO integrate into va_ajax

$mapPage = get_page_by_title('KARTE');
global $va_map_url;
if($mapPage != null){
	$va_map_url = get_page_link($mapPage);
}

function va_get_glossary_link ($id = null){

	$glossarPage = get_page_by_title('METHODOLOGIE');
	if($glossarPage != null){
		$link = get_page_link($glossarPage);
	}
	else {
		return '';
	}

	if($id){
		global $lang;
		global $vadb;
		$res = $vadb->get_var('SELECT Terminus_' . $lang . ' FROM Glossar WHERE Id_Eintrag = ' . $id);

		$link = add_query_arg('letter', remove_accents($res[0]), $link) . '#' . $id;
	}

	return $link;
}

function va_get_glossary_doi_link ($version = false, $id = null){
	$glossarPage = get_page_by_title('METHODOLOGIE');

	if($glossarPage != null){
		$link = va_get_doi_base_link();
		$params = ['page_id=' . $glossarPage->ID];
		$fragment = false;

		if($version !== false){
			$params[] = 'db=' . $version;
		}
	}
	else {
		return '';
	}

	if($id){
		global $lang;
		global $vadb;
		$res = $vadb->get_var('SELECT Terminus_' . $lang . ' FROM Glossar WHERE Id_Eintrag = ' . $id);

		$params[] = 'letter=' . remove_accents($res[0]);
		$fragment = $id;
	}

	$append = '?' . implode('&', $params) . ($fragment !== false? '#' . $fragment: '');
	return $link . '?urlappend=' . urlencode($append);
}

function va_get_comments_doi_link ($version = false, $id = null){
	$commentsPage = get_page_by_title('KOMMENTARE');

	if($commentsPage != null){
		$link = va_get_doi_base_link();
		$params = ['page_id=' . $commentsPage->ID];
		$fragment = false;

		if($version !== false){
			$params[] = 'db=' . $version;
		}
	}
	else {
		return '';
	}

	if($id){
		$fragment = $id;
	}

	$append = '?' . implode('&', $params) . ($fragment !== false? '#' . $fragment: '');
	return $link . '?urlappend=' . urlencode($append);
}


function va_get_doi_base_link (){
	return 'http://dx.doi.org/10.5282/verba-alpina';
}

function va_get_glossary_link_and_title ($id = null){

	$glossarPage = get_page_by_title('METHODOLOGIE');
	if($glossarPage != null){
		$link = get_page_link($glossarPage);
	}
	else {
		return '';
	}

	if($id){
		global $lang;
		global $vadb;
		$res = $vadb->get_var('SELECT Terminus_' . $lang . ' FROM Glossar WHERE Id_Eintrag = ' . $id);

		$link = add_query_arg('letter', $res[0], $link) . '#' . $id;
	}

	return [$link, $res];
}

function va_get_map_link ($element = null){
	global $va_map_url;
	$result = '';

	if($va_map_url){
		$result = $va_map_url;
		if($element != null){
			$result = add_query_arg('single', $element, $result);
		}
	}
	return $result;
}

function va_get_comments_link ($id = null){
	$commentsPage = get_page_by_title('KOMMENTARE');
	if($commentsPage != null){
		return get_page_link($commentsPage) . ($id ? '#' . $id : '');
	}
	return '';
}

function va_surround ($str_arr, $q){
	foreach($str_arr as $key => $str) {
		$str_arr[$key] = $q . $str . $q;
	}
	return $str_arr;
}


function va_format_bibliography ($author, $title, $year, $loc, $link, $band, $in, $seiten, $verlag, $link_abgesetzt = true){
		$res =  $author;
		if($year != '')
			$res .= ' (' . $year . ')';
		$res .= (($author == '' && $year == '')? '': ': ') . $title;
		if($loc != '')
			$res .= ', ' . $loc;
		if($in != '')
			$res .= ', in ' . $in;
		if($band != '')
			$res .= ', vol. ' . $band;
		if($verlag != '')
			$res .= ', ' . $verlag;
		if($seiten != '')
			$res .= ', ' . $seiten;
		if($link != '')
			if($link_abgesetzt)
				$res .= "\n<br /><br />\n<a href='" . str_replace("'", '%27', $link) . "'>Link</a>";
			else
				$res .= "\n(<a href='$link'>Link</a>)";
		return $res;
}

function va_format_base_type ($str, $uncertain = '0'){
	global $Ue;
	if(mb_strpos($str, '*') !== false){
		return $str . ' (* = ' . $Ue['REKONSTRUIERT'] . ')';
	}
	if($uncertain === '1'){
		$str = '(?) ' . $str;
	}

	return $str;
}
//TODO use icons from plugin everywhere and delete icons folder in va
function va_get_glossary_help ($id, &$Ue){
	return '<a href="' . va_get_glossary_link($id) . '" target="_blank"><i class="helpsymbol far fa-question-circle" style="vertical-align: middle;" title="' . $Ue['HILFE'] . '" ></i></a>';
}

function va_get_mouseover_help ($text, &$Ue, &$db, $lang, $id_glossary = NULL){
	$res = '<i class="helpsymbol far fa-question-circle va_mo_help" style="vertical-align: middle;"></i>';
	$res .= '<div style="display : none;">' . nl2br($text);
	if($id_glossary != NULL){
		$entry_name = $db->get_var('SELECT Terminus_' . $lang . ' FROM Glossar WHERE Id_Eintrag = ' . $id_glossary);
		$res .= '<br /><br />' . '<a href="' . va_get_glossary_link($id_glossary) . '" target="_blank">(' . $Ue['SIEHE'] . ' ' . $entry_name . ')</a>';
	}
	return $res . '</div>';
}

//TODO use plugin icons
/**
 * Use jQuery('.infoSymbol').qtip(); to show qtips.
 */
function va_get_info_symbol ($info_text){
	return '<img  src="' . VA_PLUGIN_URL . '/images/Help.png" style="vertical-align: middle;" title="' . $info_text . '" class="infoSymbol" />';
}


/**
 * Replaces urls that contain page_id=<german_page_id> with a valid url in the respective language
 */
function va_translate_url ($url){
	if(get_current_blog_id() != 1){
		$url = preg_replace_callback('/(?:page_id=)([0-9]*)/', function ($matches){
			$elements_linked = mlp_get_linked_elements(intval($matches[1]), '', 1);
			return 'page_id=' . $elements_linked[get_current_blog_id()];
		}, $url);
		return preg_replace('/http:\/\/www.verba-alpina.gwi.uni-muenchen.de\//', get_home_url(), $url);
	}
	return $url;
}

function va_format_lex_type ($orth, $lang, $word_class, $gender, $affix, &$Ue = NULL){
	if($Ue){
		if(isset($Ue['ABK_' . $lang])){
			$lang = $Ue['ABK_' . $lang];
		}
	}

	if($lang && $gender)
		 $result = $orth . ' (' . $lang . '.' . json_decode('"\u00A0"') . $gender . '.)';
	else if ($lang)
		$result = $orth . ' (' . $lang . '.)';
	else if ($gender)
		$result = $orth . ' (' . $gender . '.)';
	else
		$result = $orth;
	return $result;
}

function va_format_version_number ($version){
	if($version == '')
		return '';

	return substr($version, 0, 2) . '/' . substr($version, 2);
}

function token_ops (){
	global $va_xxx;

	if($_POST['stage'] == 'getTokens'){
		switch ($_POST['type']){
			case 'original':
				$tokens = $va_xxx->get_results("
					SELECT distinct Token, Erhebung FROM Tokens LEFT JOIN VTBL_Token_Konzept USING (Id_Token) LEFT JOIN Konzepte USING (Id_Konzept) JOIN Stimuli USING (ID_Stimulus) JOIN Bibliographie ON Erhebung = Abkuerzung
					WHERE VA_Beta
						AND Original = '' AND Token != '' AND (Id_Konzept is null or Id_Konzept != 779)", ARRAY_N);
				break;

			case 'bsa':
				$tokens = $va_xxx->get_results("SELECT distinct Aeusserung, Erhebung FROM Aeusserungen JOIN Stimuli USING (ID_Stimulus) WHERE Erhebung = 'BSA' AND Bemerkung NOT like '%BayDat-Transkription%'", ARRAY_N);
				break;

			default:
				$tokens = array();
		}
		echo json_encode($tokens);
	}
	die;
}
 function va_sub_translate ($str, &$Ue){
	return preg_replace_callback('/Ue\[(.*)\]/U', function ($matches) use (&$Ue){
		if(isset($Ue[$matches[1]])){
			return $Ue[$matches[1]];
		}
		return $matches[1];
	}, $str);
 }

 function va_translate($term, &$Ue){
 	if(isset($Ue[$term])){
 		return $Ue[$term];
 	}
 	return $term;
 }

function va_create_glossary_citation ($id, &$Ue){
 	global $vadb;
 	global $lang;
 	global $va_current_db_name;

 	$authors = $vadb->get_col("SELECT CONCAT(Name, ', ', SUBSTR(Vorname, 1, 1), '.') FROM VTBL_Eintrag_Autor JOIN Personen USING (Kuerzel) WHERE Aufgabe = 'auct' AND Id_Eintrag = $id ORDER BY Name ASC, Vorname ASC");
	$title = $vadb->get_var("SELECT Terminus_$lang FROM Glossar WHERE Id_Eintrag = $id");
	$link = va_get_glossary_doi_link(substr($va_current_db_name, 3, 3), $id);

 	return	implode(' / ', $authors) . ': s.v. “' . $title . '”, in: VA-' . substr(get_locale(), 0, 2) . ' ' .
 	 	substr($va_current_db_name, 3, 2) . '/' . substr($va_current_db_name, 5) . ', ' . $Ue['METHODOLOGIE'] . ', ' . $link;
 }

function va_create_glossary_bibtex ($id, &$Ue, $html = false){
	global $vadb;
	global $lang;
	global $va_current_db_name;

	$authors = $vadb->get_results("SELECT Name, Vorname FROM VTBL_Eintrag_Autor JOIN Personen USING (Kuerzel) WHERE Aufgabe = 'auct' AND Id_Eintrag = $id ORDER BY Name ASC, Vorname ASC", ARRAY_A);
	$title = $vadb->get_var("SELECT Terminus_$lang FROM Glossar WHERE Id_Eintrag = $id");
	$year = '20' . substr($va_current_db_name, 3, 2);
	$shortcode = implode('', array_map(function ($e) {return strtolower(remove_accents($e['Name']));}, $authors))
		. $year
		. str_replace(' ', '', substr(strtolower(remove_accents($title)), 0, 15));
	$link = va_get_glossary_doi_link(substr($va_current_db_name, 3, 3), $id);

	$tab = $html? '&nbsp;&nbsp;&nbsp;': "\t";
	$newline = $html? '<br />' : "\n";

	$res = '@incollection{' . $shortcode . ',' . $newline .
	$tab . 'author={' . implode(' and ', array_map(function ($e) {return $e['Name'] . ', ' . $e['Vorname'];}, $authors)) . '},' . $newline .
	$tab . 'year={' . $year . '},' . $newline .
	$tab . 'title={' . $title. '},' . $newline .
	$tab . 'publisher={VerbaAlpina-' . substr(get_locale(), 0, 2) . ' ' . va_format_version_number(substr($va_current_db_name, 3)) . '},' . $newline .
	$tab . 'booktitle={'. $Ue['METHODOLOGIE']. '},' . $newline .
	$tab . 'url={' . $link. '}' . $newline . '}';

	if($html)
		$res = htmlentities($res);

	return $res;
 }

function va_remove_special_chars ($str){
 	return preg_replace('/[^a-zA-Z0-9]/', '', remove_accents($str));
}

function va_create_comment_citation ($id, &$Ue){
 	global $vadb;
 	global $lang;
 	global $va_current_db_name;
 	if(va_version_newer_than('va_171')){
 		$authors = $vadb->get_col("SELECT CONCAT(Name, ', ', SUBSTR(Vorname, 1, 1), '.') FROM VTBL_Kommentar_Autor JOIN Personen USING (Kuerzel) WHERE Aufgabe = 'auct' AND Id_Kommentar = '$id' ORDER BY Name ASC, Vorname ASC");
 	}
 	else {
 		$content = $vadb->get_var("SELECT Comment FROM im_comments WHERE Id = '$id'");
 		$pos_auct = mb_strpos($content, '(auct. ');
 		if($pos_auct === false)
 			return false;

 		$authorStr = mb_substr($content, $pos_auct + 7, mb_strpos($content, ')', $pos_auct) - $pos_auct - 7);
 		$authorsL = mb_split('|', $authorStr);

 		$authors = array();
 		foreach ($authorsL as $author){
 			$names = mb_split(' ', $author);
 			$authors[] = $names[count($names) - 1] . ', ' . $names[0][0] . '.';
 		}
 	}
 	$title = va_sub_translate($vadb->get_var("SELECT getEntryName('$id', '$lang')"), $Ue);

 	$link = va_get_comments_doi_link(substr($va_current_db_name, 3, 3), $id);

 	return	implode(' / ', $authors) . ': s.v. “' . $title . '”, in: VA-' . substr(get_locale(), 0, 2) . ' ' .
 			substr($va_current_db_name, 3, 2) . '/' . substr($va_current_db_name, 5) . ', Lexicon alpinum, ' . $link;
}

 function va_version_newer_than ($version){
 	global $va_current_db_name;

 	if($version == 'va_xxx')
 		return false;
 	if($va_current_db_name == 'va_xxx')
 		return true;

 	$num_curr = substr($va_current_db_name, 3);
 	$num_newer = substr($version, 3);

 	return $num_curr > $num_newer;
 }

 //Php ??
 function dq ($array, $val){
 	if (isset($array[$val]) && $array[$val])
 		return $array[$val];
 	return null;
 }

 function va_only_latin_letters($str){
 	return preg_replace('/[^a-zA-Z]/', '', remove_accents($str));
 }

 function va_two_dim_to_assoc($two_dim){
 	$assoc = [];
 	foreach ($two_dim as $val){
 		$assoc[$val[0]] = $val[1];
 	}
 	return $assoc;
 }

 function va_echo_new_concept_fields ($name, $extra_fields = NULL){
	$fields = array(
			new IM_Field_Information('Name_D', 'V', false),
			new IM_Field_Information('Beschreibung_D', 'V', true),
			new IM_Field_Information('Id_Kategorie AS Kategorie', 'F{CONCAT(Hauptkategorie, "/", Kategorie)}', true),
			new IM_Field_Information('Taxonomie', 'V', false),
			new IM_Field_Information('QID', 'N', false, false, NULL, false, true),
			new IM_Field_Information('Kommentar_Intern', 'V', false),
			new IM_Field_Information('Relevanz', 'B', false, true, true),
			new IM_Field_Information('Pseudo', 'B', false, true),
			new IM_Field_Information('Grammatikalisch', 'B', false, true),
			new IM_Field_Information('VA_Phase', 'E', false)
	);

	if($extra_fields){
		foreach ($extra_fields as $extra){
			$fields[] = $extra;
		}
	}

	echo im_table_entry_box ($name, new IM_Row_Information('Konzepte', $fields, 'Angelegt_Von'));
}

function va_add_interval ($intervals, $new_interval){

	if(empty($intervals)){
		return [$new_interval];
	}

	$len = count($intervals);

	//New interval at the beginning
	if ($new_interval[1] < $intervals[0][0]){
		array_unshift($intervals, $new_interval);
		return $intervals;
	}

	//New interval at the end
	if ($new_interval[0] > $intervals[$len - 1][1]){
		$intervals[] = $new_interval;
		return $intervals;
	}

	$startInterval = NULL;
	//Find starting interval
	foreach ($intervals as $index => $interval){
		if($new_interval[0] >= $interval[0] && $new_interval[0] <= $interval[1]){
			$startInterval = [$index, true];
			break;
		}

		if($new_interval[0] < $interval[0]){
			$startInterval = [$index, false];
			break;
		}
	}

	//Find ending interval
	$endInterval = NULL;
	foreach ($intervals as $index => $interval){
		if($new_interval[1] >= $interval[0] && $new_interval[1] <= $interval[1]){
			$endInterval =  [$index, true];
			break;
		}

		if ($index == $len - 1 || $intervals[$index + 1][0] > $new_interval[1]){
			$endInterval = [$index, false];
			break;
		}
	}

	if(!$startInterval[1] && !$endInterval[1]){
		array_splice($intervals, max($endInterval[0], $startInterval[0]), $endInterval[0] - $startInterval[0] + 1, [$new_interval]);
	}
	else if ($startInterval[1]){
		if($endInterval[1]){
			array_splice($intervals, $startInterval[0], $endInterval[0] - $startInterval[0] + 1, [[$intervals[$startInterval[0]][0], $intervals[$endInterval[0]][1]]]);
		}
		else {
			array_splice($intervals, $startInterval[0], $endInterval[0] - $startInterval[0] + 1, [[$intervals[$startInterval[0]][0], $new_interval[1]]]);
		}
	}
	else {
		array_splice($intervals, $startInterval[0], $endInterval[0] - $startInterval[0] + 1, [[$new_interval[0], $intervals[$endInterval[0]][1]]]);
	}
	return $intervals;

}

function va_add_marking_spans ($text, $intervals, $span_attributes = 'style="background: yellow"'){
	if(count($intervals) == 0){
		return htmlentities($text);
	}

	$offset = 0;
	$marked_text = $text;

	foreach ($intervals as $index => $interval){
		$pre = '<span ' . $span_attributes . '>';
		$post = '</span>';
		$marked = substr($marked_text, $interval[0] + $offset, $interval[1] - $interval[0]);
		$middle = htmlentities($marked);

		$start = substr($marked_text, 0, $interval[0] + $offset);
		$end = substr($marked_text, $interval[1] + $offset);

		if ($index == count($intervals) - 1){
			$end = htmlentities($end);
		}

		if ($index == 0){
			$startEnt = htmlentities($start);
			$offset += strlen($startEnt) - strlen($start);
			$start = $startEnt;
		}

		$marked_text =	$start . $pre . $middle . $post . $end;
		$offset += strlen($pre) + strlen($post) + (strlen($middle) - strlen($marked));
	}

	return $marked_text;
}

function va_strip_intervals ($text, $intervals){

	$offset = 0;
	$stripped_text = $text;
	foreach ($intervals as $interval){
		$stripped_text =
		substr($stripped_text, 0, $interval[0] - $offset) . substr($stripped_text, $interval[1] - $offset);
		$offset += $interval[1] - $interval[0];
	}

	return $stripped_text;
}

function va_reconstruct_record_from_tokens ($tokens){

	$curr_1 = 0;
	$curr_2 = 0;
	$curr_3 = 0;
	$cur_token = '';
	$cur_gender = 'xxx';

	$res = '';

	foreach ($tokens as $index => $token){
		if(intval($token['Ebene_1']) === $curr_1 + 1 && intval($token['Ebene_2']) === 1 && intval($token['Ebene_3']) === 1){
			if($res == ''){
				$res = $token['Token'];
			}
			else {
				$res .= ';' . $token['Token'];
			}
		}
		else if (intval($token['Ebene_1']) === $curr_1 && intval($token['Ebene_2']) === $curr_2 + 1 && intval($token['Ebene_3']) === 1){
			if($cur_token != $token['Token'] || $cur_gender == $token['Genus']){ //Double tokens for different genders!
				$res .= ',' . $token['Token'];
			}
		}
		else if (intval($token['Ebene_1']) === $curr_1 && intval($token['Ebene_2']) === $curr_2 && intval($token['Ebene_3']) === $curr_3 + 1){
			$space = ' ';
			if($tokens[$index-1]['Trennzeichen']){
				$space = $tokens[$index-1]['Trennzeichen'];
			}

			$res .= $space . $token['Token'];
		}
		else {
			throw new Exception('Invalid token indexes: [' . $token['Ebene_1'] . ',' . $token['Ebene_2'] . ',' . $token['Ebene_3'] . '] after [' .
				$curr_1 . ',' . $curr_2 . ',' . $curr_3 . '] for record ' . $token['Id_Aeusserung'] . '!');
		}

		$curr_1 = intval($token['Ebene_1']);
		$curr_2 = intval($token['Ebene_2']);
		$curr_3 = intval($token['Ebene_3']);
		$cur_token = $token['Token'];
		$cur_gender = $token['Genus'];
	}

	return $res;
}

function va_deep_assoc_array_compare ($arr1, $arr2){

	foreach ($arr2 as $key => $val){
		if(!array_key_exists($key, $arr1)){
			return 'Key "' . $key . '" does not exist in array 1!';
		}
	}

	foreach ($arr1 as $key => $val){
		if(!array_key_exists($key, $arr2)){
			return 'Key "' . $key . '" does not exist in array 2!';
		}

		if(is_array($val)){
			if(is_array($arr2[$key])){
				$rec = va_deep_assoc_array_compare($val, $arr2[$key]);
				if($rec !== true){
					return 'Key "' . $key . '" sub-array not equal: ' . $rec;
				}
			}
			else {
				return 'Key "' . $key . '" is array in array 1, but no array in array 2!';
			}
		}
		else {
			if($val !== $arr2[$key]){
				return 'Key "' . $key . '" has value "' . $val . '" in array 1 and value "' . $arr2[$key] . '" in array 2!';
			}
		}
	}
	return true;
}

function va_array_to_html_string ($arr, $showLevel = 1, $recLevel = 0){
	if(empty($arr)){
		return '[]';
	}

	$assoc = count(array_filter(array_keys($arr), 'is_string')) > 0;

	$res = '';
	$first = true;
	$vals = [];

	foreach ($arr as $key => $val){
		if (is_array($val)){
			$vals[] = ($assoc? '"' . $key . '" => ': '') . va_array_to_html_string($val, $showLevel, $recLevel + 1);
		}
		else {
			$vals[] = ($assoc? '"' . $key . '" => ': '') . ($val === null? 'NULL' : (is_string($val)? ('"' . htmlentities($val) . '"') : htmlentities($val)));
		}
	}

	return '[' . ($recLevel > $showLevel? '' : '<br />') . implode(($recLevel > $showLevel? ', ': '<br />'), $vals) . ($recLevel > $showLevel? '' : '<br />') . ']' . ($recLevel > 0? '' : '<br />');
}

function va_concept_compare ($t1, $t2, $search){
	$diff = mb_stripos ($t1, $search) - mb_stripos ($t2, $search);
	if($diff == 0){
		return strcmp($t1, $t2);
	}
	else {
		return $diff;
	}
}

//Preg replace with ignoring html tags
function va_replace_in_text ($pattern, $callback, $subject){
	if ($subject == '')
		return '';

	$doc = new IvoPetkov\HTML5DOMDocument();
	$doc->loadHTML($subject);

	$node = va_replace_in_single_html_node($doc, $pattern, $callback);
	return substr($doc->saveHTML($node), 28, -14);
}

function va_replace_in_single_html_node (DOMNode $node, $pattern, $callback){

	if ($node->nodeType === XML_TEXT_NODE){
		$newNode = $node->ownerDocument->createDocumentFragment();
		$newText = preg_replace_callback($pattern, $callback, $node->nodeValue);
		$newNode->appendXML(str_replace('&', '&amp;', $newText));
		return $newNode;
	}
	else {
		if ($node->hasChildNodes()){
			$replacements = [];
			foreach ($node->childNodes as $child){
				$newChild = va_replace_in_single_html_node($child, $pattern, $callback);
				$replacements[] = [$child, $newChild];
			}

			foreach ($replacements as $rep){
				$node->replaceChild($rep[1], $rep[0]);
			}
		}

		return $node;
	}
}

function va_get_general_beta_parser($source) {
    global $general_beta_parser;

    if (!$general_beta_parser){
        $general_beta_parser = new VA_BetaParser($source);
    }

    return $general_beta_parser;
}
?>