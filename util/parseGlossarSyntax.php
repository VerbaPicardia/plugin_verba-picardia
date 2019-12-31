<?php
function parseSyntax(&$value, $replaceNewlines = false, $intern = false, $mode = 'A') {
	
	set_error_handler('va_syntax_warning_handler', E_WARNING);
	
	try {
		global $media_path;
		global $map_path;
		global $comment_path;
		global $lang;
		
		$media_path = get_site_url (1) . '/wp-content/uploads/';
		$map_path = va_get_map_link ();
		$comment_path = va_get_comments_link ();
		
		if ($replaceNewlines) {
			$value = nl2br ($value);
			// Re-convert new lines after tags to newlines
			$value = preg_replace ('#><br />#', '>', $value);
		}
		
		// " - " -> " – "
		$value = preg_replace ('/ - /', ' – ', $value);
		
		// Aufzählungen
		$value = va_parse_list_syntax('*', 'ul', 'li', $value);
		$value = va_parse_list_syntax('#', 'ol', 'li', $value);
		
		
		// Escape-Zeichen
		$value = preg_replace ('/\\\\\\\\/', '\\\\', $value);
		$value = preg_replace ('/\\\\\*/', '*', $value);
		$value = preg_replace ('/\\\\#/', '#', $value);
		$value = preg_replace ('/\\\\</', '&lt;', $value);
		$value = preg_replace ('/\\\\>/', '&gt;', $value);
		
		// Kein wp_texturize in auskommentieren [[..]] Befehlen
		$value = preg_replace ('/(-\[\[.*\]\])/U', '<code>$1</code>', $value);
		
		// Bilder
		$value = preg_replace ('/(?<!-)\[\[Bild:([^\|]*)\]\]/U', "<br /><img src=\"$media_path$1\" /><br />", $value);
		
		$value = preg_replace_callback ('/(?<!-)\[\[Bild:(.*)\|(.*)\]\]/U', function ($treffer) {
			global $media_path;
			if (strpos ($treffer[2], ':')) {
				$parts = explode (':', $treffer[2]);
				if (strcasecmp ($parts[0], 'Breite')) {
					return "$treffer[0]<br /><img src=\"$media_path$treffer[1]\" width=\"$parts[1]px\" /><br />";
				} else if (strcasecmp ($parts[0], 'Höhe')) {
					return "$treffer[0]<br /><img src=\"$media_path$treffer[1]\" height=\"$parts[1]px\" /><br />";
				} else {
					return "$treffer[0]<br /><img src=\"$media_path$treffer[1]\"><br />";
				}
			} else {
				return "$treffer[0]<br /><img src=\"$media_path$treffer[1]\"  width=\"$treffer[2]\" /><br />";
			}
		}, $value);
		
		// Themenkarte
		$value = preg_replace_callback ('/(?<!-)\[\[(?:([^\]]*)\|)?Karte:([^|]*)(\|Popup)?\]\]/U', function ($treffer) {
			global $map_path;
			
			$map = $treffer[2];
			if (! $treffer[1]) {
				$label = $treffer[2];
			} else {
				$label = $treffer[1];
			}
			
			$murl = $map_path;
			if (! is_numeric ($map)) {
				$map = getThemenkarteId ($map);
			}
			
			global $vadb;
			$options = $vadb->get_var ($vadb->prepare ('SELECT Options FROM IM_Syn_Maps WHERE Id_Syn_Map = %d', $map));
			$options = json_decode ($options, true);
			$dbset = $options['tdb'];
			global $va_next_db_name;
			if ($dbset == $va_next_db_name){ //The synoptic map stores a future version
				$dbset = 'xxx';
			}
			$murl = add_query_arg ('db', $dbset, $murl);
			
			if (isset($treffer[3])) { //PHP omits the last element if it is empty...
				return "<a target='_BLANK' href=\"" . add_query_arg ('tk', $map, $murl) . "\" onclick=\"window.open(this.href,this.target,'width=1024,height=768'); return false;\">$label</a>";
			} else {
				return "<a target='_BLANK' href=\"" . add_query_arg ('tk', $map, $murl) . "\">$label</a>";
			}
		}, $value);
		
		$is_lex = false;
		global $post;
		if ($post && $post->post_title === 'KOMMENTARE') {
			$is_lex = true;
		}
		
		// Kommentare
		$value = preg_replace_callback ('/(?<!-)\[\[(([^\]]*)\|)?Kommentar:(.)(.*)\]\]/U', function ($treffer) use ($is_lex, $lang) {
			global $comment_path;
			$prefix = $treffer[3];
			$id = $treffer[4];
			if ($is_lex) {
				$start = '<a';
			} else {
				$start = "<a target='_BLANK'";
			}
			
			if ($treffer[1] == '') {
				$name = getCommentHeadline ($prefix . $id, $lang);
				return "$start href='$comment_path#$prefix$id'>$name</a>";
			} else {
				return "$start href='$comment_path#$prefix$id'>$treffer[2]</a>";
			}
		}, $value);
		
		parseBiblio ($value);
		
		// Ergänzungen/Änderungen
		$value = preg_replace_callback ('#(<|\[\[)neu(>|\]\])#', function ($treffer) use ($intern) {
			if ($intern)
				return '<div style="background-color: #dffde1; display: inline-block;">';
			else
				return '';
		}, $value);
		
		$value = preg_replace_callback ('#(<|\[\[)mod(>|\]\])#', function ($treffer) use ($intern) {
			if ($intern)
				return '<div style="background-color: #e6f7ff; display: inline-block;">';
			else
				return '';
		}, $value);
		
		$value = preg_replace_callback ('#(<|\[\[)anm(>|\]\])#', function ($treffer) use ($intern) {
			if ($intern)
				return '<div style="background-color: #fafad2; display: inline-block;">';
			else
				return '';
		}, $value);
		
		$value = preg_replace_callback ('#((<|\[\[)/neu(>|\]\]))|((<|\[\[)/mod(>|\]\]))|((<|\[\[)/anm(>|\]\]))#', function ($treffer) use ($intern) {
			if ($intern)
				return '</div>';
			else
				return '';
		}, $value);
		
		// SQL
		$value = preg_replace_callback ('/(?<!-)\[\[SQL:(.*)((?:\|(?:db|width|height|id)=.*)*)\]\]/sU', function ($treffer) {
			global $va_current_db_name;
			$atts['db'] = $va_current_db_name;
			$atts['query'] = $treffer[1];
			$atts['login'] = 'va_wordpress';
			if (count ($treffer) > 2) {
				$params = explode ('|', $treffer[2]);
				foreach ( $params as $p ) {
					$pair = explode ('=', $p);
					if ($pair[0]) {
						$atts[$pair[0]] = $pair[1];
					}
				}
			}
			return sth_parseSQL ($atts);
		}, $value);
		
		// Seiten
		$value = preg_replace_callback ('/(?<!-)\[\[(([^\]]*)\|)?Seite:(.*)\]\]/U', function ($treffer) {
			global $Ue;
			$page = get_page_by_title ($treffer[3]);
			if (! $page) {
				return 'PAGE NOT FOUND!';
			}
			$link = get_page_link ($page->ID);
			if ($treffer[1] == '') {
				return "<a href='" . $link . "'>" . $Ue[$treffer[3]] . "</a>";
			} else {
				return "<a href='" . $link . "'>" . $treffer[2] . "</a>";
			}
		}, $value);
		
		// Lokale UND globale Links
		$value = preg_replace_callback ('/(?<!-)\[\[(?!(?:Abk:|Konst:))(.*)\]\]/U', function ($treffer) use ($mode) {
			global $media_path;
			global $lang;
			global $vadb;
			$parts = explode ('|', $treffer[1]);
			
			$beschreibung = $parts[0];
			
			// Link ohne Beschreibung
			if (count ($parts) == 1) {
				$eintrag = $parts[0];
			} 
			else {
				$image = stristr ($parts[1], "Bild:");
				// Link auf Bild
				if ($image) {
					return "<a href=\"" . $media_path . substr ($parts[1], 5) . "\">$beschreibung</a>";
				}			// Link mit Beschreibung
				else {
					$eintrag = $parts[1];
				}
			}
			
			$eintrag = trim ($eintrag);
			
			if ($eintrag == '')
				return '';
			
			if (strpos ($eintrag, "http") === 0)
				return "<a href=\"$eintrag\" target=\"_BLANK\">$beschreibung</a>";
			
			$url = va_get_glossary_link ();
			if ($mode === 'A') {
				$id = $vadb->get_var ("SELECT Id_Eintrag FROM Glossar WHERE Terminus_$lang = '" . addslashes ($eintrag) . "'");
				$url = add_query_arg ('letter', $eintrag[0], $url) . '#' . $id;
			} else {
				$tags = $vadb->get_row ("SELECT Id_Eintrag, Id_Tag FROM Glossar LEFT JOIN VTBL_Eintrag_Tag USING (Id_Eintrag) WHERE Terminus_$lang = '" . addslashes ($eintrag) . "'", ARRAY_N);
				$url = add_query_arg ('tag', $tags[1] ? $tags[1] : 0, $url) . '#' . $tags[0];
			}
			return "<a href=\"" . $url . "\">$beschreibung</a>";
		}, $value);
		
		// Vorgestelltes Minus (Escape-Zeichen) entfernen
		$value = preg_replace ('/-\[/', '[', $value);
		
		//Abkürzungen
		$value = va_add_abrv($value);
		
		//Explizite Abkürzungen
		$value = preg_replace_callback('/(?<!-)\[\[Abk:([^|]*)\|([^\]]*)\]\]/', function ($treffer){
			return '<span class="sabr" title="' . htmlspecialchars($treffer[2]) . '">' . $treffer[1] . '</span>';
		}, $value);
			
		//Ausnahmen bei Abkürzungen
		$value = preg_replace('/(?<!-)\[\[Konst:([^\]]*)\]\]/', '$1', $value);
	}
	catch (ErrorException $e){
		$value = '<span style="color: red">' . $e->getMessage() . '</span>';
	}
	
	restore_error_handler();
}
function va_create_bibl_html($abk, $descr = null) {
	if ($descr == null)
		$descr = $abk;
	
	$code = preg_replace ('/\s+/', '', $abk);
	$code = str_replace ('/', '', $code);
	$code = str_replace ('.', '', $code);
	
	return [
		$code,
		"<span class='bibl' data-bibl='$code'>$descr</span>"
	];
}

function va_add_bibl_div($code, $content, &$codesBibl) {
	if (! array_key_exists ($code, $codesBibl)) {
		$codesBibl[$code] = "<div id='$code' style='display: none;'>
			$content
			</div>";
	}
}

function getThemenkarteId($name) {
	global $vadb;
	return $vadb->get_var ($vadb->prepare ("SELECT Id_Syn_Map FROM im_syn_maps WHERE Name = %s", $name));
}

function getCommentHeadline($id, $lang) {
	global $vadb;
	return $vadb->get_var ($vadb->prepare ("SELECT getEntryName(%s, %s)", $id, $lang));
}

function parseBiblio(&$text) {
	$codesBibl = array();
	
	$text = preg_replace_callback ('/([^-])\[\[(([^\[]*)\|)?Bibl:([^\[]*)\]\]/', function ($treffer) use (&$codesBibl) {
		global $vadb;
		$b = $vadb->get_results ("SELECT Autor, Titel, Ort, Jahr, Download_URL, Band, Enthalten_In, Seiten, Verlag FROM Bibliographie WHERE Abkuerzung = '$treffer[4]'", 'ARRAY_N');
		$abk = $treffer[3] ? $treffer[3] : null;
		list ($code, $html) = va_create_bibl_html ($treffer[4], $abk);
		va_add_bibl_div ($code, ((sizeof ($b) == 0) ? 'Eintrag nicht gefunden' : va_format_bibliography ($b[0][0], $b[0][1], $b[0][3], $b[0][2], $b[0][4], $b[0][5], $b[0][6], $b[0][7], $b[0][8])), $codesBibl);
		return $treffer[1] . $html;
	}, 
	$text);
	
	$text .= implode ('', $codesBibl);
}

function va_add_abrv($value) {
	if (va_version_newer_than ('va_172')) {
		$abr_list = [];
		
		global $vadb;
		global $lang;
		$abks = $vadb->get_results ("
				SELECT Abkuerzung, Bedeutung FROM Abkuerzungen WHERE Sprache = '$lang' OR Sprache = 'ALL'
					UNION
				SELECT CONCAT(Abkuerzung, '.') AS Abkuerzung, CONCAT(Bezeichnung_$lang, IF(ISO639 = '3', ' (ISO 639-3)', '')) AS Bedeutung FROM Sprachen WHERE Bezeichnung_$lang != '' AND (ISO639 = '3' OR ISO639 = '')", ARRAY_A);
		
		$abk_map = [];
		
		foreach ($abks as $abk){
			$abk_map[$abk['Abkuerzung']] = $abk['Bedeutung'];
		}
			
		$value = va_replace_in_text ('/(?<=\s|\(|<|\x{00c2}|-|\x{00a0}|>|\/|\[)(' . implode('|', array_map(function ($abk){
			return preg_quote($abk['Abkuerzung']);
		}, $abks)) . ')(?=\s|\.|,|;|\)|\]|:|>|\x{00c2}|-|\x{00a0}|<)/', function ($treffer) use (&$abk_map, &$abr_list) {
			$cleaned_abr = str_replace ('.', 'DOT', $treffer[1]);
			if (! isset ($abr_list[$treffer[1]])) {
				$abr_list[$treffer[1]] = '<div id="ABR_' . $cleaned_abr . '" style="display: none;">' . $abk_map[$treffer[1]] . '</div>';
			}
			return '<span class="vaabr" data-vaabr="' . $cleaned_abr . '">' . $treffer[1] . '</span>';
		}, $value);
		
		return $value . implode ('', $abr_list);
	}
	return $value;
}

function va_syntax_warning_handler ($errno, $errstr) {
	throw new ErrorException($errstr);
}

function va_parse_list_syntax($char, $main, $sub, $value){
	$char_quoted = preg_quote($char);
	
	//Add list tags
	$num_repl = 1;
	$len = 1;
	while ($num_repl > 0) {
		$value = preg_replace (
			'/^' . $char_quoted . '{' . $len . ',}.+([\n\r]' . $char_quoted . '.+)*/m',
			"<" . $main . ">\n$0\n</" . $main . ">" . ($len > 1? '</' . $sub . '>' : ''),
			$value, - 1, $num_repl);
		$len++;
	}
	
	//Add element tags to all elements that are not followed by a sub-list
	$value = preg_replace(
		'@^' . $char_quoted . '+(.+)(?=[\n\r](' . $char_quoted . '|</' . $main . '>))@m',
		'<' . $sub . '>$1</' . $sub . '>' , $value);
	
	//Add opening tag to elements followed by sublist (closing element is added to the end of the sub-list in the first part)
	$value = preg_replace(
		'/^' . $char_quoted . '+(.+)/m',
		'<' . $sub . '>$1' , $value);
	
	return $value;
}
?>