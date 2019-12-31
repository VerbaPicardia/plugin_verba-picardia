<?php
function overview_page (){
	global $va_xxx;
	
	$va_xxx->query("CALL buildConceptCount()");
	
	$ajax_params = array (
		'action' => 'va',
		'namespace' => 'overview'
	);
	$ajaxurl = admin_url( 'admin-ajax.php' );
	
	?>
	<script type="text/javascript">
		jQuery(function (){
			jQuery("#tabDiv").tabs();
		});
	</script>
	
	<article>
		<div class="entry-content" id="tabDiv" style="margin-right: 50px">
			<ul>
				<li>
					<a href="<?php 
					$ajax_params['query'] = 'transcription';
					echo $ajaxurl . '?' . http_build_query($ajax_params);
					?>">Transkription</a>
				</li>
				<li>
					<a href="<?php 
					$ajax_params['query'] = 'stimuli';
					echo $ajaxurl . '?' . http_build_query($ajax_params);
					?>">Stimuli/Konzepte</a>
				</li>
				<li>
					<a href="<?php 
					$ajax_params['query'] = 'atlases';
					echo $ajaxurl . '?' . http_build_query($ajax_params);
					?>">Atlanten/Konzepte</a>
				</li>
			</ul>
		</div>
	</article>
	<?php
}
?>