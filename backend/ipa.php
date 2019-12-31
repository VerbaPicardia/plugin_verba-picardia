<?php

function ipa_page (){
	global $va_xxx;

	$sources = $va_xxx->get_results("SELECT
										Erhebung,
										COUNT(DISTINCT Token) AS Gesamt,
										COUNT(DISTINCT CASE WHEN IPA = '' AND NOT EXISTS (SELECT * FROM Sonderzeichen WHERE Zeichen = Token) THEN Token END) AS Ohne
									FROM Stimuli s JOIN Tokens USING (Id_Stimulus) JOIN Bibliographie ON Erhebung = Abkuerzung LEFT JOIN VTBL_Token_Konzept USING (Id_Token)
									WHERE Token != '' AND (Id_Konzept is null or Id_Konzept != 779) AND VA_IPA
									GROUP BY Erhebung", ARRAY_A);
	?>

	<script type="text/javascript">
		var tokenNumbers = {
		<?php
		foreach ($sources as $source){
			echo '"' . $source['Erhebung'] . '" : [' . $source['Gesamt'] . ', ' . $source['Ohne'] . '],';
		}
		?>
		};

		var parserBeta;
		var parserBSA;
		var parserALD;

		jQuery(function (){
			jQuery("#ipaSelectSource").val("0");
			jQuery("#IPAonlyMissing").prop("checked", true);
			parserBeta = peg.generate(jQuery("#grammarBeta").val());
			parserBSA = peg.generate(jQuery("#grammarBSA").val());
			parserALD = peg.generate(jQuery("#grammarALD").val());
			jQuery("#grammarBeta").toggle(false);

			jQuery("#ipaSelectSource").on("change", function (){
				if(this.value != "0"){
					jQuery("#ipaMainArea").toggle(true);
					jQuery("#numberAll").val(tokenNumbers[this.value][0]);
					jQuery("#numberWithout").val(tokenNumbers[this.value][1]);
					jQuery("#numberHandled").val(0);
					jQuery("#ipaResults").val("");
					jQuery("#ipaErrors").val("");
				}
			});
		});

		function computeIPA (){
			jQuery("#ipaResults").val("");
			jQuery("#ipaErrors").val("");
			jQuery("#numberHandled").val("0");

			jQuery("#IPARegular").toggle(false);
			jQuery("#IPALoading").toggle(true);

			var source = jQuery("#ipaSelectSource").val();
			var all = !jQuery("#IPAonlyMissing").prop("checked");
			if(all){
				tokenNumbers[source][1] = jQuery("#numberAll").val() * 1;
				jQuery("#numberWithout").val(jQuery("#numberAll").val());
			}

			jQuery.post(ajaxurl, {
				"action" : "va",
				"namespace" : "ipa",
				"query" : "get_tokens",
				"source" : source,
				"all" : all
			}, function(response) {
				var list = [];
				tokens = JSON.parse(response);
				for (var i = 0, j = tokens.length; i < j; i++) {
					try {
						if(source == "BSA"){
							var tokenL = parserBSA.parse(tokens[i]);
						}
						else if (source == "ALD-I" || source == "ALD-II"){
							var tokenL = parserALD.parse(tokens[i]);
						}
						else {
							var tokenL = parserBeta.parse(tokens[i]);
						}
						list.push(tokenL);
					} catch (err) {
						jQuery("#ipaErrors").val(jQuery("#ipaErrors").val() + "Token: " + tokens[i] + " ungültig!  (" + err + ")\n\n");
						jQuery("#numberHandled").val(jQuery("#numberHandled").val() * 1 + 1);
					}
				};


				computeIPAForTokens(list, 0, 50, source);

			});
		}

		function computeIPAForTokens (list, index, step, source){
			if(index < list.length){
				var subList = list.slice(index, index + step);

				jQuery.post(ajaxurl, {
					"action" : "va",
					"namespace" : "ipa",
					"query" : "compute",
					"source" : source,
					"data" : JSON.stringify(subList)
				}, function(response) {
					var textArray = JSON.parse(response);
					jQuery("#ipaResults").val(jQuery("#ipaResults").val() + textArray[0]);
					jQuery("#ipaErrors").val(jQuery("#ipaErrors").val() + textArray[1]);
					jQuery("#numberHandled").val(jQuery("#numberHandled").val() * 1 + subList.length);
					tokenNumbers[source][1] -= textArray[2] * 1;
					jQuery("#numberWithout").val(tokenNumbers[source][1]);
					computeIPAForTokens(list, index + step, step, source);
				});
			}
			else {
				jQuery("#IPARegular").toggle(true);
				jQuery("#IPALoading").toggle(false);
			}
		}
	</script>

	<h1>Beta -> IPA</h1>

	<br />

	<select id="ipaSelectSource">
		<option value = "0">--- Quelle wählen ---</option>
		<?php
		foreach ($sources as $source){
			echo '<option value="' . $source['Erhebung'] . '">' . $source['Erhebung'] . '</option>';
		}
		?>
	</select>

	<br />
	<br />

	<div id="ipaMainArea" style="display: none">
		<table>
			<tr>
				<td>Unterschiedliche Tokens gesamt:</td>
				<td><input id="numberAll" type="text" style="width: 50pt" readonly /></td>
			</tr>
			<tr>
				<td>Ohne IPA Darstellung:</td>
				<td><input id="numberWithout" type="text" style="width: 50pt" readonly /></td>
			</tr>
			<tr>
				<td>Bearbeitet:</td>
				<td><input id="numberHandled" type="text" style="width: 50pt" readonly /></td>
			</tr>
		</table>

		<br />

		<div id="IPARegular" style="display: inline">
			<input type="button" class="button button-primary" value="IPA berechnen" onClick="computeIPA()" />
			&nbsp;&nbsp;
			<input id="IPAonlyMissing" type="checkbox" /> Nur Tokens ohne IPA-Darstellung
		</div>

		<div id="IPALoading" style="display: none">
			<img src="<?php echo VA_PLUGIN_URL . '/images/Loading.gif';?>" />
		</div>


		<br />
		<br />

		<table>
			<tr>
				<td>Ergebnis</td>
				<td>Fehler</td>
			</tr>
			<tr>
				<td><textarea id="ipaResults" cols="40" rows="20"></textarea></td>
				<td><textarea id="ipaErrors" cols="120" rows="20"></textarea></td>
			</tr>
		</table>
	</div>

	<div style="display : none">
		<textarea id="grammarBeta"><?php echo file_get_contents(plugin_dir_path(__FILE__) . '/grammars/grammatik_transkr.txt'); ?></textarea>
		<textarea id="grammarBSA"><?php echo file_get_contents(plugin_dir_path(__FILE__) . '/grammars/grammatik_bsa.txt'); ?></textarea>
		<textarea id="grammarALD"><?php echo file_get_contents(plugin_dir_path(__FILE__) . '/grammars/grammatik_ald.txt'); ?></textarea>
	</div>
	<?php
}
?>