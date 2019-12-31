<?php

$srcDir = '../../../Interactive-Map_Plugin/src';

echo "Build PHP...\n";
$phar = new Phar('im_live.phar');
$phar->buildFromDirectory($srcDir . '/php');
$phar->addFile('../db.php', 'db.php');
$phar->addFile('../va_map.php', 'va_map.php');
$phar->setStub(file_get_contents("stub.php"));

echo "Build CSS...\n";
$css_files = array(
	'../va_map.css',
	$srcDir . '/../src/css/styles.css',
	$srcDir . '/../src/css/google-maps.css'
);

$css = fopen('im_live.css', 'w');
foreach ($css_files as $cf){
	$in = fopen($cf, 'r');
	while ($line = fgets($in)){
		fwrite($css, $line);
	}
	fclose($in);
}
fclose($css);

echo "Build JS...\n";
echo shell_exec('ant -file compile_all.ant compileGM');
echo shell_exec('ant -file compile_all.ant compilePixi');
copy('../../../Interactive-Map_Plugin/compiled/interactive_map_compiled_gm.js', 'im_live_gm.js');
copy('../../../Interactive-Map_Plugin/compiled/interactive_map_compiled_pixi.js', 'im_live_pixi.js');

echo 'Done';