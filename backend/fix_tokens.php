<?php

add_action('wp_ajax_test_token_original', 'test_token_original');
function test_token_original() {
    global $va_xxx;

    $tokens = json_decode(stripslashes($_POST ['data']));

    foreach ( $tokens as $token ) {
        $complete = true;
        $quelle = $token [0];
        $result = '';

        $has_span = false;

        foreach ( $token [1] as $index => $character ) {

            $original = $va_xxx->get_var("SELECT IF(Original REGEXP '^$', Hex_Original, Original) from Codepage_Original WHERE Beta = '" . addslashes($character) . "'");
            if ($original) {
                if (strpos($original, "<span style='position: absolute;") !== false) {
                    $result .= '<span style="position: relative">' . $original . '</span>';
                    $has_span = true;
                } else {
                    $result .= $original;
                }
            } else {
                echo "Eintrag \"$character\" fehlt!\n";
                $complete = false;
            }
        }
        if ($complete) {

            if ($has_span) {
                $result = '<span style="position : relative">' . $result . '</span>'; // Add this to make the absolute spans work
            }

            echo implode('', $token [1]) . ' -> ' . $result . "\n";
            $va_xxx->query("UPDATE Tokens SET Original = '" . addslashes($result) . "', Trennzeichen_Original = (SELECT Original FROM Codepage_Original WHERE Beta = Trennzeichen)
			WHERE EXISTS (SELECT * FROM Stimuli WHERE Stimuli.Id_Stimulus = Tokens.Id_Stimulus AND Erhebung = '$quelle') AND Token = '" . addslashes(implode('', $token [1])) . "'");
        }
    }

    die();
}

add_action('wp_ajax_test_codepage_original', 'test_codepage_original');
function test_codepage_original() {
    global $va_xxx;

    $tokens = $va_xxx->get_results("SELECT Beta FROM Codepage_Original WHERE Original regexp '^$'", ARRAY_N);

    echo json_encode($tokens);

    die();
}

add_action('wp_ajax_test_codepage_original2', 'test_codepage_original2');
function test_codepage_original2() {
    global $va_xxx;

    $transl = json_decode(stripslashes($_POST ['data']));

    foreach ( $transl as $t ) {
        $uni = '';
        $hex = preg_replace('/x([0-9a-fA-F]?[0-9a-fA-F][0-9a-fA-F][0-9a-fA-F][0-9a-fA-F]+)/u', '&#x$1;', $t [1]);

        $uni = mb_convert_encoding($hex, 'UTF-8', 'HTML-ENTITIES');

        if (strpos($t [0], 'u1') === false) {
            $va_xxx->query("UPDATE Codepage_Original SET Original = '" . addslashes($uni) . "', Hex_Original = '" . addslashes($hex) . "' WHERE Beta = '" . addslashes($t [0]) . "'");
            echo $t [0] . ' -> ' . $hex . " | " . $uni . "\n";
        } else {
            echo "UPDATE Codepage_Original SET Original = '" . addslashes($uni) . "', Hex_Original = '" . addslashes($hex) . "' WHERE Beta = '" . addslashes($t [0]) . "';\n";
        }
    }

    die();
}

function fixHexUnicode($string) {
    $json = json_encode($string);
    $json = str_replace('\\\\u', '\\u', $json);
    return json_decode($json);
}

function test_token_ipa() {
    global $va_xxx;

    $tokens = json_decode(stripslashes($_POST ['data']));
    $missing_chars = array ();

    foreach ( $tokens as $token ) {
        $quelle = $token [0];
        $akzente = $va_xxx->get_results("SELECT Beta, IPA FROM Codepage_IPA WHERE Art = 'Akzent' AND Erhebung = '$quelle'", ARRAY_N);
        $vokale = $va_xxx->get_var("SELECT group_concat(DISTINCT SUBSTR(Beta, 1, 1) SEPARATOR '') FROM Codepage_IPA WHERE Art = 'Vokal' AND Erhebung = '$quelle'", 0, 0);
        $complete = true;
        $result = '';

        foreach ( $token [1] as $index => $character ) {
            foreach ( $akzente as $akzent ) {
                $ak_qu = preg_quote($akzent [0], '/');
                $character = preg_replace_callback('/([' . $vokale . '][^' . $ak_qu . 'a-zA-Z]*)' . $ak_qu . '/', function ($matches) use (&$result, $akzent) {
                    $result .= $akzent [1];
                    return $matches [1];
                }, $character);
            }

            $ipa = $va_xxx->get_results("SELECT IPA from Codepage_IPA WHERE Erhebung = '" . $quelle . "' AND Beta = '" . addcslashes($character, "\'") . "' AND IPA != ''", ARRAY_N);
            if ($ipa [0] [0]) {
                $result .= $ipa [0] [0];
            } else {
                if (! in_array($character, $missing_chars)) {
                    $missing_chars [] = $character;
                    echo "Eintrag \"$character\" fehlt fuer \"$quelle\"!\n";
                }
                $complete = false;
            }
        }
        if ($complete) {
            echo implode('', $token [1]) . ' -> ' . $result . "\n";
            $va_xxx->query("UPDATE Tokens SET IPA = '" . addslashes($result) . "', Trennzeichen_IPA = (SELECT IPA FROM Codepage_IPA WHERE Art = 'Trennzeichen' AND Beta = Trennzeichen AND Erhebung = '$quelle')
			 WHERE EXISTS (SELECT * FROM Stimuli WHERE Stimuli.Id_Stimulus = Tokens.Id_Stimulus AND Erhebung = '$quelle') AND Token = '" . addslashes(implode('', $token [1])) . "'");
        }
    }

    die();
}

function fix_tokens() {
    ?>
<script type="text/javascript">
	var parser, parser2, parserBSA;
	jQuery(function() {
		parser = peg.generate(jQuery("#grammar").val());
		parserBSA = peg.generate(jQuery("#grammarBSA").val())
		parser2 = peg.generate(jQuery("#grammar2").val());
	});

	function tokensOriginal() {
		jQuery.post(ajaxurl, {
			"action" : "token_ops",
			"stage" : "getTokens",
			"type" : "original"
		}, function(response) {
			var list = [];
			tokens = JSON.parse(response);
			jQuery("#ges").val(tokens.length);
			jQuery("#akt").val("0");
			jQuery("#result").val("");
			for (var i = 0, j = tokens.length; i < j; i++) {
				try {
					var tokenL = parser.parse(tokens[i][0]);
					list.push([tokens[i][1], tokenL]);
				} catch (err) {
					jQuery("#result").val(jQuery("#result").val() + "Token: " + tokens[i] + " ungültig!  (" + err + ")\n");
				}
				jQuery("#akt").val(jQuery("#akt").val() * 1 + 1);
			};
			jQuery.post(ajaxurl, {
				"action" : "test_token_original",
				"data" : JSON.stringify(list)
			}, function(response) {
				jQuery("#result").val(jQuery("#result").val() + response);
			});
		});
	}

	function codepageOriginal (){
		jQuery("#result").val("");
		jQuery.post(ajaxurl, {
			"action" : "test_codepage_original"
		}, function(response) {
			var list = [];
			betas = JSON.parse(response);

			for (var i = 0, j = betas.length; i < j; i++) {
				try {
					var betaL = parser2.parse(betas[i][0]);
					list.push([betas[i][0], betaL]);
				} catch (err) {
					jQuery("#result").val(jQuery("#result").val() + "Beta: <<" + betas[i] + ">> ungültig!  (" + err + ")\n");
				}
			};
			jQuery.post(ajaxurl, {
				"action" : "test_codepage_original2",
				"data" : JSON.stringify(list)
			}, function(response) {
				jQuery("#result").val(jQuery("#result").val() + response);
			});
		});
	}

	var completeList = new Set();
	function combBSA (index){
		jQuery.post(ajaxurl, {
			"action" : "va",
			"namespace" : "test",
			"query" : "getPVA_BSA_Tokens",
			"index" : index
		}, function(response) {
			var result = JSON.parse(response);
			if(result.length > 0){
				for (var i = 0; i < result.length; i++){
					try {
						var tokenL = parserBSA.parse(result[i]);
						for (var j = 0; j < tokenL.length; j++){
							if(!completeList.has(tokenL[j])){
								completeList.add(tokenL[j]);
								jQuery("#result").val(jQuery("#result").val() + tokenL[j] + "\n");
							}
						}
					} catch (err) {
						jQuery("#errors").val(jQuery("#errors").val() + "Äußerung: " + result[i] + " ungültig!  (" + err + ")\n");
					}
				}
				combBSA(index + 1000);
			}
		});
	}
</script>

<div style="display : none;">
    <textarea id="grammar"><?php echo file_get_contents(plugin_dir_path(__FILE__) . '/grammars/grammatik_transkr.txt'); ?></textarea>
</div>

<div style="display : none;">
    <textarea id="grammar2"><?php echo file_get_contents(plugin_dir_path(__FILE__) . '/grammars/grammatik_original.txt'); ?></textarea>
</div>

<div style="display : none;">
    <textarea id="grammarBSA"><?php echo file_get_contents(plugin_dir_path(__FILE__) . '/grammars/grammatik_bsa.txt'); ?></textarea>
</div>

<h1>Fix tokens display</h1>

<input class="button button-primary" type="button" value="Fix Tokens" onClick="tokensOriginal()" />

<br />
<br />

<input type="text" id="akt" value="" />
von
<input type="text" id="ges" value="" />

<br />

<p>Results</p>
<textarea rows="20" style="width: 100%;" id="result"></textarea>

<p>Errors</p>
<textarea rows="20" style="width: 100%;" id="errors"></textarea>

<?php
}
?>