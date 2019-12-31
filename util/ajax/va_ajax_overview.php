<?php
function va_ajax_overview (&$db){
	switch ($_REQUEST['query']){
		case 'transcription':
			va_overview_build_transcription();
			break;

		case 'stimuli':
			va_overview_build_stimuli_concepts();
			break;

		case 'atlases':
			va_overview_build_atlases_concepts();
			break;
	}
}

function va_overview_build_atlases_concepts (){
	global $va_xxx;
	?>
	<table class="matrix">
			<thead>
				<tr>
					<th><div>Konzept</div></th>
					<?php
					$atlanten = $va_xxx->get_col('SELECT DISTINCT Erhebung FROM Stimuli s WHERE EXISTS (SELECT * FROM Aeusserungen a WHERE a.Id_Stimulus = s.Id_Stimulus)', 0);
					foreach ($atlanten as $atlas){
						echo '<th><div><span>' . $atlas . '</span></div></th>';
					}
					?>
				</tr>
			</thead>

			<tbody>

			<?php
			/*
				 SELECT
					IF(Name_D = '', Beschreibung_D, Name_D) AS Konzept,
					Id_Aeusserung,
					Erhebung
				FROM
					((SELECT * FROM A_ueberkonzepte_erweitert)
						UNION ALL
					(SELECT Id_Konzept, Id_Konzept as Id_Ueberkonzept FROM Konzepte)) u
					JOIN Konzepte k ON u.Id_Ueberkonzept = k.Id_Konzept
					JOIN Ueberkonzepte ue ON k.Id_Konzept = ue.Id_Konzept
					JOIN VTBL_Aeusserung_Konzept v ON u.Id_Konzept = v.Id_Konzept
					JOIN Aeusserungen USING (Id_Aeusserung)
					JOIN Stimuli USING (Id_Stimulus)
				WHERE
					Relevanz
					AND (Name_D != '' OR ue.Id_Ueberkonzept = 707)
					AND (Aeusserung IS NULL OR (Aeusserung != '<vacat>' AND Aeusserung != '<problem>'))
				GROUP BY u.Id_Ueberkonzept, Id_Aeusserung, Id_Stimulus
				ORDER BY IF(Name_D = '', Beschreibung_D, Name_D)
			 */
			$belege = $va_xxx->get_results("
				SELECT
					IF(Name_D = '', Beschreibung_D, Name_D) AS Konzept,
					Id_Aeusserung,
					Erhebung,
					Basiskonzept
				FROM
					((SELECT * FROM A_ueberkonzepte_erweitert)
						UNION ALL
					(SELECT Id_Konzept, Id_Konzept as Id_Ueberkonzept FROM Konzepte)) u
					JOIN Konzepte k ON u.Id_Ueberkonzept = k.Id_Konzept
					JOIN VTBL_Aeusserung_Konzept v ON u.Id_Konzept = v.Id_Konzept
					JOIN Aeusserungen USING (Id_Aeusserung)
					JOIN Stimuli USING (Id_Stimulus)
					LEFT JOIN A_Konzept_Tiefen a ON a.Id_Konzept = k.Id_Konzept
				WHERE
					(Aeusserung IS NULL OR (Aeusserung != '<vacat>' AND Aeusserung != '<problem>')) AND k.RELEVANZ
				GROUP BY u.Id_Ueberkonzept, Id_Aeusserung, Id_Stimulus
				ORDER BY Basiskonzept DESC, IF(Basiskonzept, Konzept, IF(Tiefe IS NULL, 99, Tiefe)) ASC, Konzept ASC
			", ARRAY_A);

			$matrix = array();
			$vorschlag = array();

			foreach ($belege as $beleg){
				$matrix[$beleg['Konzept']] = array();
				foreach ($atlanten as $atlas){
					$matrix[$beleg['Konzept']][$atlas] = 0;
				}
				$vorschlag[$beleg['Konzept']] = $beleg['Basiskonzept'];
			}

			foreach ($belege as $beleg){
				$matrix[$beleg['Konzept']][$beleg['Erhebung']] ++;
			}


			foreach ($matrix as $key => $row){
				if($vorschlag[$key])
					echo '<tr style="background: green; color : white">';
				else
					echo '<tr>';
				echo '<td><div>';
				echo $key;
				echo '</div></td>';
				foreach ($row as $val){
					echo '<td><div>';
					echo ($val > 0? 'X': '');
					echo '</div></td>';
				}
				echo '</tr>';
			}
			?>
			</tbody>
		</table>
	<?php
}

function va_overview_build_stimuli_concepts (){
	global $va_xxx;

	$konzepte = $va_xxx->get_results("
	SELECT
		k.Id_Konzept,
		IF(Name_D = '', Beschreibung_D, NAme_D) as Konzept,
		GROUP_CONCAT(DISTINCT CONCAT(Erhebung, '#', Karte, '_', Nummer) SEPARATOR ', ') as Stimuli,
		count(DISTINCT Id_Aeusserung) as Aeusserungen,
		count(DISTINCT IF(Tokenisiert, Id_Aeusserung, NULL)) as Tokenisiert
	FROM
		((SELECT * FROM A_ueberkonzepte_erweitert)
			UNION ALL
		(SELECT Id_Konzept, Id_Konzept as Id_Ueberkonzept FROM Konzepte)) u
		JOIN Konzepte k ON u.Id_Ueberkonzept = k.Id_Konzept
		JOIN Ueberkonzepte ue ON k.Id_Konzept = ue.Id_Konzept
		LEFT JOIN VTBL_Aeusserung_Konzept v ON u.Id_Konzept = v.Id_Konzept
		LEFT JOIN Aeusserungen USING (Id_Aeusserung)
		LEFT JOIN Stimuli USING (Id_Stimulus)
	WHERE
		Relevanz
		AND (Name_D != '' OR ue.Id_Ueberkonzept = 707)
		AND (Aeusserung IS NULL OR (Aeusserung != '<vacat>' AND Aeusserung != '<problem>'))
	GROUP BY u.Id_Ueberkonzept
	ORDER BY IF(Name_D = '', Beschreibung_D, Name_D)
	", ARRAY_A);
	?>
	<table class="easy-table easy-table-default tablesorter   tablesorter-default">
		<tr>
			<th>Konzept</th>
			<th>Stimuli</th>
			<th>Äußerungen</th>
			<th>Tokenisiert</th>
			<th>Tokens</th>
		</tr>
		<?php
		foreach ($konzepte as $konzept){
			echo '<tr>';
			echo '<td>' . $konzept['Konzept'] . '</td>';
			echo '<td>' . $konzept['Stimuli'] . '</td>';
			echo '<td>' . $konzept['Aeusserungen'] . '</td>';
			echo '<td>' . $konzept['Tokenisiert'] . '</td>';

			$subConcepts = $va_xxx->get_col('SELECT Id_Konzept FROM A_Ueberkonzepte_Erweitert WHERE Id_Ueberkonzept = ' . $konzept['Id_Konzept'], 0);
			$subConcepts[] = $konzept['Id_Konzept'];

			$tokens = $va_xxx->get_var('
				SELECT count(*)
				FROM
					Tokens
					JOIN VTBL_Token_Konzept v USING (Id_Token)
				WHERE
					Id_Konzept IN (' . implode(',', $subConcepts) . ')'
				, 0, 0);

			$tokengruppen = $va_xxx->get_var('
				SELECT count(*)
				FROM
					Tokengruppen
					JOIN VTBL_Tokengruppe_Konzept v USING (Id_Tokengruppe)
				WHERE
					Id_Konzept IN (' . implode(',', $subConcepts) . ')'
				, 0, 0);

			echo '<td>' . ($tokens + $tokengruppen) . '</td>';

			echo '</tr>';
		}
		?>
	</table>
	<?php
}

function va_overview_build_transcription (){
	global $va_xxx;
	$stimuli = $va_xxx->get_results("
	SELECT
		Id_Stimulus,
		Erhebung,
		concat(Karte, '_', Nummer) as Karte,
		Stimulus,
		(SELECT count(DISTINCT Id_Informant) FROM Aeusserungen WHERE Id_Stimulus = Stimuli.Id_Stimulus AND (SELECT Alpenkonvention FROM Informanten WHERE Id_Informant = Aeusserungen.Id_Informant)) AS Aeusserungen,
		(SELECT count(*) FROM Informanten WHERE Erhebung = Stimuli.Erhebung AND Alpenkonvention) as Informanten,
		(SELECT count(*) FROM Aeusserungen WHERE Id_Stimulus = Stimuli.Id_Stimulus AND Aeusserung = '<problem>') AS Probleme
	FROM Stimuli
    ORDER BY Stimulus, Erhebung
	", ARRAY_A);

	?>
	<table class="easy-table easy-table-default tablesorter   tablesorter-default">
		<thead>
			<tr>
				<td>Id</td>
				<td>Erhebung</td>
				<td>Karte</td>
				<td>Stimulus</td>
				<td>Transkribiert</td>
				<td>Probleme</td>
			</tr>
		</thead>
	<?php

	foreach ($stimuli as $stimulus) {
		if($stimulus['Informanten'] == 0)
			continue;

		$style = '';
		if($stimulus['Aeusserungen'] === $stimulus['Informanten']){
			if($stimulus['Probleme'] == 0){
				$style .= 'background: #00FF00;';
			}
			else {
				$style .= 'background: #FFFF00;';
			}
		}
		else if($stimulus['Aeusserungen'] != 0){
			$style .= 'background: #00FFFF;';
		}

		echo '<tr style="' . $style . '">';
		echo '<td>' . $stimulus['Id_Stimulus'] . '</td>';
		echo '<td>' . $stimulus['Erhebung'] . '</td>';
		echo '<td>' . $stimulus['Karte'] . '</td>';
		echo '<td>' . $stimulus['Stimulus'] . '</td>';
		echo '<td>' . $stimulus['Aeusserungen'] . '/' . $stimulus['Informanten'] . '</td>';
		echo '<td>' . $stimulus['Probleme'] . '</td>';
		echo '</tr>';
	}
	?>
	</table>
	<?php
}
?>