<?php 
	session_start();

	if (isset($_POST['deletekeyname'])) {
		unset($_SESSION["form_files"][$_POST['deletekeyname']]);
		echo "1";
		die();
	}

	class ExtendedZip extends ZipArchive {

	    // Member function to add a whole file system subtree to the archive
	    public function addTree($dirname, $localname = '') {
	        if ($localname)
	            $this->addEmptyDir($localname);
	        $this->_addTree($dirname, $localname);
	    }

	    // Internal function, to recurse
	    protected function _addTree($dirname, $localname) {
	        $dir = opendir($dirname);
	        while ($filename = readdir($dir)) {
	            // Discard . and ..
	            if ($filename == '.' || $filename == '..')
	                continue;

	            // Proceed according to type
	            $path = $dirname . '/' . $filename;
	            $localpath = $localname ? ($localname . '/' . $filename) : $filename;
	            if (is_dir($path)) {
	                // Directory: add & recurse
	                $this->addEmptyDir($localpath);
	                $this->_addTree($path, $localpath);
	            }
	            else if (is_file($path)) {
	                // File: just add
	                $this->addFile($path, $localpath);
	            }
	        }
	        closedir($dir);
	    }

	    // Helper function
	    public static function zipTree($dirname, $zipFilename, $flags = 0, $localname = '') {
	        $zip = new self();
	        $zip->open($zipFilename, $flags);
	        $zip->addTree($dirname, $localname);
	        $zip->close();
	    }
	}

	// download dot file
	if (isset($_GET['download'])) {
		if (isset($_SESSION["last_dotscript"])) {
			header('Content-type: text/vnd.graphviz');
			header('Content-Disposition: attachment; filename="graph.gv"');
			header('Pragma: no-cache');
			ob_clean();
			echo $_SESSION["last_dotscript"];
		}
		die();
	}

	// donwload zip with all source code
	if (isset($_GET['downloadsource'])) {
		$zipFile = "./source-code.zip";
		
		if (file_exists($zipFile)) {
			/*
			if (time()-filemtime($zipFile) > 2 * 3600) {
			  	// file older than 2 hours
				unlink($zipFile);
				ExtendedZip::zipTree('./', $zipFile, ZipArchive::CREATE);
			}
			*/
		} else {
			ExtendedZip::zipTree('./', $zipFile, ZipArchive::CREATE);
		}

		if (file_exists($zipFile)) {
			header('Content-Type: application/zip, application/octet-stream');
			header('Content-Length: ' . filesize($zipFile));
			header("Content-Disposition: attachment; filename=\"" . basename($zipFile) . "\";");
			header("Content-Transfer-Encoding: binary");
			ob_end_flush();
			readfile($zipFile);
		}
		die();
	}
 ?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>UFO/XCOM Research Graph Generator</title>
	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" type="text/css" media="screen" href="css/autoComplete.css">
	<script src="js/viz.js"></script>
	<script src="js/full.render.js"></script>
	<script src="js/FileSaver.js"></script>
	<script src="js/jquery-3.5.1.min.js"></script>
	<script src="js/panzoom.js"></script>
	
	<script src="js/autoComplete.js"></script>
	<script>
	$( document ).ready(function() {
		$(".form-box-toggle a.toggle-options").click(function(e) {
			e.preventDefault();
			$(".code-box").removeClass( "show" );
			$(".form-box").toggleClass( "show" );

			if (!($(".code-box").hasClass("show")) && !($(".form-box").hasClass("show"))) {
				$(".container-box").addClass( "wide" );
			} else {
				$(".container-box").removeClass( "wide" );
			}
		});

		$(".form-box-toggle a.toggle-graphviz-code").click(function(e) {
			e.preventDefault();
			$(".form-box").removeClass( "show" );
			$(".code-box").toggleClass( "show" );

			if (!($(".code-box").hasClass("show")) && !($(".form-box").hasClass("show"))) {
				$(".container-box").addClass( "wide" );
			} else {
				$(".container-box").removeClass( "wide" );
			}
		});

		$(".loaded-list-files li a").click(function(e) {
			e.preventDefault();

			var thisli = $(this).parent();
			var keyfilename = $(this).data("keyfilename");

			$.post( "", { deletekeyname: keyfilename }, function( data ) {
			  	if (data == 1) {
					thisli.remove();
			  	}
			});
		});
	});
	</script>
</head>
<body>
	<pre>
<?php 
	require_once __DIR__ . '/vendor/autoload.php';
	//use Graphp\Algorithms\ConnectedComponents as AlgorithmConnected;
	use Symfony\Component\Yaml\Yaml;

	require_once __DIR__ . '/ConnectedComponentsCustom.php';
	use Graphp\Algorithms\ConnectedComponentsCustom as AlgorithmConnectedCustom;


	if(isset($_POST['submit']))
	{
		// save form options on session
		$_SESSION["form_group-base"] = $_POST['group-base'];

		$_SESSION["form_optionUseUnlocks"] = (isset($_POST['optionUseUnlocks'])) ? 1 : 0;
		$_SESSION["form_optionUseRequires"] = (isset($_POST['optionUseRequires'])) ? 1 : 0;
		$_SESSION["form_optionUseDependencies"] = (isset($_POST['optionUseDependencies'])) ? 1 : 0;
		$_SESSION["form_optionUseLookup"] = (isset($_POST['optionUseLookup'])) ? 1 : 0;
		$_SESSION["form_optionUseGetOneFree"] = (isset($_POST['optionUseGetOneFree'])) ? 1 : 0;
		$_SESSION["form_optionUseGetOneFreeProtected"] = (isset($_POST['optionUseGetOneFreeProtected'])) ? 1 : 0;;

		$_SESSION["form_optionLimitNodeName"] = strtoupper(trim($_POST['optionLimitNodeName']));
		$_SESSION["form_optionFollowDir"] = $_POST['optionFollowDir'];
		$_SESSION["form_optionMaxDepth"] = $_POST['optionMaxDepth'];


		function remove_utf8_bom($text)
		{
		    $bom = pack('H*','EFBBBF');
		    $text = preg_replace("/^$bom/", '', $text);
		    return $text;
		}

		function rearrange($files)
		{
			foreach($files as $key1 => $val1) {
				foreach($val1 as $key2 => $val2) {
					for ($i = 0, $count = count($val2); $i < $count; $i++) {
						$newFiles[$i][$key2] = $val2[$i];
					}
				}
			}
			return $newFiles;
		}

		function loadAndMerge($fileyaml)
		{
			global $yaml_master;

			// fix for urf8-bom files.
			//$yaml_override = Yaml::parse(remove_utf8_bom(file_get_contents($filename)));
			$yaml_override = Yaml::parse($fileyaml);

			//var_dump($yaml_override);
			/*
			print_r($yaml_override['research']);
			die();
			*/

			if (!isset($yaml_override['research'])) {
				return;
			}

			$yaml_override = $yaml_override['research'];


			foreach ($yaml_override as $vo) {
				
				if (isset($vo['name'])) {
					$is_merged = false;
					foreach ($yaml_master as &$vm) {
						if ($vm['name'] == $vo['name']) {
							// merge exist
							$vm = $vo + $vm;
							//var_dump($vm);
							$is_merged = true;
						}
					}
					if (!$is_merged) {
						// add new to master
						$yaml_master[] = $vo;
					}				
				}

				if (isset($vo['delete'])) {
					// remove previous declaratin if exists
					foreach ($yaml_master as $key => &$vm) {
						if ($vm['name'] == $vo['delete']) {
							unset($yaml_master[$key]);
						}
					}
				}
			}
			
		}

		$yaml_master = [];

		// load base research file

		if ($_POST['group-base'] == 1) {
			$yaml_master = Yaml::parse(file_get_contents('xcom1/research.rul'));
			$yaml_master = $yaml_master['research'];
		}

		if ($_POST['group-base'] == 2) {
			$yaml_master = Yaml::parse(file_get_contents('xcom2/research.rul'));
			$yaml_master = $yaml_master['research'];
		}


		// load and merge extra mod files
		/*
		$fileList = glob('uploads/*.rul');
		foreach($fileList as $filename){
			//Use the is_file function to make sure that it is not a directory.
			if(is_file($filename)){
				//echo $filename, '<br>'; 
				loadAndMerge($filename);
			}   
		}
		*/

		
		$files = rearrange($_FILES);

		foreach ($files as $file) {
			if (UPLOAD_ERR_OK === $file['error']) {
				//$fileName = basename($file['name']);
				//echo $fileName;

				// save files to session
				$_SESSION["form_files"][$file['name']] = remove_utf8_bom(file_get_contents($file['tmp_name']));

				//loadAndMerge($file['tmp_name']);
				
				//echo $file['tmp_name'];
				//move_uploaded_file($file['tmp_name'], $uploadDir.DIRECTORY_SEPARATOR.$fileName);
			}
		}

		// elaborate files
		foreach ($_SESSION["form_files"] as $sessionfile) {
			//echo $sessionfile;
			loadAndMerge($sessionfile);
		}
		

		/*
		if (!empty($_FILES)) {
			var_dump($_FILES);
			foreach($_FILES as $file){
			  echo $file['name']; 
			}
		}
		*/

		
		//loadAndMerge('research2.rul');
		//loadAndMerge('research3.rul');

		//$yamlString = Yaml::dump($yaml);
		//var_dump($yamlString);

		//var_dump($yaml_master);

		// REF
		// https://github.com/graphp/graphviz#quickstart-examples


		$graph = new Fhaculty\Graph\Graph();
		//$graph->setAttribute('graphviz.graph.layout', 'fdp');
		// $graph->setAttribute('graphviz.graph.layout', 'dot');

		$graph->setAttribute('graphviz.graph.rankdir', 'LR');
		$graph->setAttribute('graphviz.graph.overlap', 'false');
		$graph->setAttribute('graphviz.graph.compound', 'true');
		//$graph->setAttribute('graphviz.graph.overlap', 'scale');
		//$graph->setAttribute('graphviz.graph.splines', 'true');

		$graph->setAttribute('graphviz.graph.pad', '0.4');
		$graph->setAttribute('graphviz.graph.ranksep', '0.4');
		$graph->setAttribute('graphviz.graph.nodesep', '0.8');


		// colors
		// https://www.graphviz.org/doc/info/colors.html

		// https://www.ufopaedia.org/index.php/Ruleset_Reference_Nightly_(OpenXcom)#Research


		$arrayNodes = [];

		// parse to DOT output
		foreach ($yaml_master as $v) {
			
			if (!isset($arrayNodes[$v['name']])) {
				$arrayNodes[$v['name']] = $graph->createVertex($v['name']);
			}

			if (isset($v['needItem']) && $v['needItem'] == true) {
				$arrayNodes[$v['name']]->setAttribute('graphviz.color', 'blue');
			} else {

				if ((!isset($v['cost']) || $v['cost'] == 0) && (!isset($v['points']) || $v['points'] == 0)) {
					$arrayNodes[$v['name']]->setAttribute('graphviz.color', 'magenta');
				} else {
					$arrayNodes[$v['name']]->setAttribute('graphviz.color', 'black');
				}
			}

			if (isset($v['unlockFinalMission']) && $v['unlockFinalMission'] == true) {
				$arrayNodes[$v['name']]->setAttribute('graphviz.shape', 'circle');
			}


			if ($_SESSION["form_optionUseUnlocks"]) {			
				if (isset($v['unlocks'])) {
					foreach ($v['unlocks'] as $u) {

						if (!isset($arrayNodes[$u])) {
							$arrayNodes[$u] = $graph->createVertex($u);
						}

						$edge = $arrayNodes[$v['name']]->createEdgeTo($arrayNodes[$u]);
						$edge->setAttribute('graphviz.color', 'black');
					}
				}
			}

			if ($_SESSION["form_optionUseRequires"]) {
				if (isset($v['requires'])) {
					foreach ($v['requires'] as $r) {

						if (!isset($arrayNodes[$r])) {
							$arrayNodes[$r] = $graph->createVertex($r);
						}

						$edge = $arrayNodes[$r]->createEdgeTo($arrayNodes[$v['name']]);
						// $edge = $arrayNodes[$v['name']]->createEdgeTo($arrayNodes[$r]);
						$edge->setAttribute('graphviz.color', 'red');
					}
				}
			}

			if ($_SESSION["form_optionUseDependencies"]) {
				if (isset($v['dependencies'])) {
					foreach ($v['dependencies'] as $d) {

						if (!isset($arrayNodes[$d])) {
							$arrayNodes[$d] = $graph->createVertex($d);
						}

						$edge = $arrayNodes[$d]->createEdgeTo($arrayNodes[$v['name']]);
						// $edge = $arrayNodes[$v['name']]->createEdgeTo($arrayNodes[$d]);
						$edge->setAttribute('graphviz.color', 'orange');
					}
				}
			}

			if ($_SESSION["form_optionUseLookup"]) {
				if (isset($v['lookup'])) {
					
					if (!isset($arrayNodes[$v['lookup']])) {
						$arrayNodes[$v['lookup']] = $graph->createVertex($v['lookup']);
					}

					$edge = $arrayNodes[$v['name']]->createEdgeTo($arrayNodes[$v['lookup']]);
					$edge->setAttribute('graphviz.color', 'gray');
				}
			}
			

			if ($_SESSION["form_optionUseGetOneFree"]) {
				if (isset($v['getOneFree'])) {
					foreach ($v['getOneFree'] as $onef) {

						if (!isset($arrayNodes[$onef])) {
							$arrayNodes[$onef] = $graph->createVertex($onef);
						}

						$edge = $arrayNodes[$v['name']]->createEdgeTo($arrayNodes[$onef]);
						$edge->setAttribute('graphviz.color', 'green');
					}
				}
			}

			// getOneFreeProtected
			/*
			https://www.ufopaedia.org/index.php/Ruleset_Reference_Nightly_(OpenXcom)

			A list of additional "bonus" research projects that may be granted when completing this project. Each topic in the list is "protected" by another topic, which allows you finer control over when the player gets the bonus.
			For example: you could divide the topics into early-game, mid-game and late-game and protect each group by some "gateway topic".

			getOneFreeProtected only works when the research project is completed in a Laboratory. All other means of obtaining a research topic ignore getOneFreeProtected.

			ex.
		    getOneFreeProtected:
		      STR_ALIEN_ORIGINS:
		        - STR_SMALL_SCOUT
		        - STR_UFO_SUBTYPES
		        - STR_ALIEN_ELECTRONICS
		      STR_ALIEN_VOCABULARY:
		        - STR_PARTICLE_MICROACCELERATION
		        - STR_ALIEN_SMALL_PLASMA_TURRET_CORPSE
		        - STR_ALIEN_LASER_TURRET_CORPSE
		      STR_DELTA_RADIATION:
		        - STR_ELERIUM_ENERGY_CONVERSION
		      STR_ALIEN_NARRATIVE:
		        - STR_FLOATERS_HISTORY
			*/

			if ($_SESSION["form_optionUseGetOneFreeProtected"]) {
				if (isset($v['getOneFreeProtected'])) {
					foreach ($v['getOneFreeProtected'] as $onefp) {

						if (!isset($arrayNodes[$onefp])) {
							$arrayNodes[$onefp] = $graph->createVertex($onefp);
						}

						$edge = $arrayNodes[$v['name']]->createEdgeTo($arrayNodes[$onefp]);
						$edge->setAttribute('graphviz.color', 'blue');
					}
				}
			}

		}

		/*
		$blue = $graph->createVertex('blue');
		$blue->setAttribute('graphviz.color', 'blue');

		$red = $graph->createVertex('red');
		$red->setAttribute('graphviz.color', 'red');

		$edge = $blue->createEdgeTo($red);
		$edge->setAttribute('graphviz.color', 'grey');
		*/

		/*
		// TODO add legend 
		// example:
		digraph {
		  rankdir=LR
		  node [shape=plaintext]
		  subgraph cluster_01 { 
			label = "Legend";
			key [label=<<table border="0" cellpadding="2" cellspacing="0" cellborder="0">
			  <tr><td align="right" port="i1">item 1</td></tr>
			  <tr><td align="right" port="i2">item 2</td></tr>
			  <tr><td align="right" port="i3">item 3</td></tr>
			  <tr><td align="right" port="i4">item 4</td></tr>
			  </table>>]
			key2 [label=<<table border="0" cellpadding="2" cellspacing="0" cellborder="0">
			  <tr><td port="i1">&nbsp;</td></tr>
			  <tr><td port="i2">&nbsp;</td></tr>
			  <tr><td port="i3">&nbsp;</td></tr>
			  <tr><td port="i4">&nbsp;</td></tr>
			  </table>>]
			key:i1:e -> key2:i1:w [style=dashed]
			key:i2:e -> key2:i2:w [color=gray]
			key:i3:e -> key2:i3:w [color=peachpuff3]
			key:i4:e -> key2:i4:w [color=turquoise4, style=dotted]
		  }
		*/

		$graphviz = new Graphp\GraphViz\GraphViz();
		$dotscript = "";

		if ($_SESSION["form_optionLimitNodeName"]) {
			if (isset($arrayNodes[$_SESSION["form_optionLimitNodeName"]])) {
				$myvertex = $arrayNodes[$_SESSION["form_optionLimitNodeName"]];
				//$graphfiltered = $graph->createGraphCloneVertices($graph->createSearch($myvertex)->getVertices());
				//var_dump($myvertex);
				//echo $myvertex->getId();
				//die();

				//$walk = Fhaculty\Graph\Walk::factoryFromVertices([$myvertex]);
				//$graphfiltered = $walk->createGraph();
				
				$max_depth = $_SESSION["form_optionMaxDepth"];

				if ($max_depth == 0) {
					// 0 = unlimited depth
					$max_depth = PHP_INT_MAX;
				}

				$alg = new AlgorithmConnectedCustom($graph);
				$graphfiltered = $alg->createGraphComponentVertex($myvertex, $_SESSION["form_optionFollowDir"], $max_depth);
			} else {
				$graphfiltered = $graph = new Fhaculty\Graph\Graph();
			}

			$dotscript = $graphviz->createScript($graphfiltered);
		} else {
			$dotscript = $graphviz->createScript($graph);
		}

		// save to session for download link
		$_SESSION["last_dotscript"] = $dotscript;

		/*
		// make image from local executable
		$graphviz->setExecutable('Graphviz\bin\dot.exe');
		$imgdata = $graphviz->createImageData($graph);
		//$graphviz->createImageHtml($graph);
		//echo $graphviz->createImageFile($graph);
		//$graphviz->display($graph);
		$filename = "graph_res";
		file_put_contents($filename.".gv", $dotscript);
		file_put_contents($filename.".png", $imgdata);
		*/
	} else {
		// default options
		$_SESSION["form_group-base"] = 1;

		$_SESSION["form_optionUseUnlocks"] = 1;
		$_SESSION["form_optionUseRequires"] = 1;
		$_SESSION["form_optionUseDependencies"] = 1;
		$_SESSION["form_optionUseLookup"] = 1;
		$_SESSION["form_optionUseGetOneFree"] = 0;
		$_SESSION["form_optionUseGetOneFreeProtected"] = 0;

		$_SESSION["form_optionLimitNodeName"] = "";
		$_SESSION["form_optionFollowDir"] = 0;
		$_SESSION["form_optionMaxDepth"] = 0;

		$_SESSION["form_files"] = [];
	}

?>
	</pre>

	<h1>UFO/XCOM Research Graph Generator (ALPHA v0.2)</h1>

	<?php if (isset($dotscript)): ?>
	<div class="form-box-toggle">
		<a href="#" class="toggle-options">OPTIONS</a>
		<a href="#" class="toggle-graphviz-code">GRAPHVIZ CODE</a>
		<div class="download-image"><a href="#" onclick="genAsSVG();return false;">Download Graph as .svg</a></div>
	</div>
	<?php endif; ?>

	<div class="container-box">

	<?php if (!isset($dotscript)): ?>
	<div class="left-side-box full-width">
	<?php else: ?>
	<div class="left-side-box">
	<?php endif; ?>	

	<?php /*
	<?php if (!isset($dotscript)): ?>
	<div class="form-box show">
	<?php else: ?>
	<div class="form-box">
	<?php endif; ?>
	*/ ?>
	<div class="form-box show">

		<form action="./" id="form-submit" method="post" enctype="multipart/form-data">
			<p class="desc">Choose the default research tree:</p>
			<fieldset id="group-base">
				<input type="radio" value="1" id="baseChoice2" name="group-base" <?php echo ($_SESSION["form_group-base"] == 1) ? 'checked' : ''; ?>>
				<label for="baseChoice2">UFO/XCOM1</label>

				<input type="radio" value="2" id="baseChoice3" name="group-base" <?php echo ($_SESSION["form_group-base"] == 2) ? 'checked' : ''; ?>>
				<label for="baseChoice3">TFTD/XCOM2</label>

				<input type="radio" value="0" id="baseChoice1" name="group-base" <?php echo ($_SESSION["form_group-base"] == 0) ? 'checked' : ''; ?>>
				<label for="baseChoice1">NONE</label>
			</fieldset>
			<br />
			<br />
			<p class="desc">Add your mod "research" .rul file(s) (will be merged with the default research tree):</p>
			<input type="file" name="userfile[]" multiple="multiple" accept=".rul" /><br />

			<?php if (count($_SESSION["form_files"])): ?>
			<ul class="loaded-list-files">
				<?php 
				foreach(array_keys($_SESSION["form_files"]) as $keyfilename) {
					echo "<li>".$keyfilename." <a href=\"#\" data-keyfilename=\"".$keyfilename."\">[remove]</a></li>";
				}
				?>
			</ul>
			<?php endif; ?>

			<br />
			<br />
			<p class="desc">Connections:</p>
			<fieldset>
  				<input type="checkbox" id="optionUseUnlocks" name="optionUseUnlocks" value="1" <?php echo ($_SESSION["form_optionUseUnlocks"] == 1) ? 'checked' : ''; ?>>
  				<label for="optionUseUnlocks" class="info-legend info-legend-unlock">unlocks</label><br>

  				<input type="checkbox" id="optionUseRequires" name="optionUseRequires" value="1" <?php echo ($_SESSION["form_optionUseRequires"] == 1) ? 'checked' : ''; ?>>
  				<label for="optionUseRequires" class="info-legend info-legend-requires">requires</label><br>

  				<input type="checkbox" id="optionUseDependencies" name="optionUseDependencies" value="1" <?php echo ($_SESSION["form_optionUseDependencies"] == 1) ? 'checked' : ''; ?>>
  				<label for="optionUseDependencies" class="info-legend info-legend-dependencies">dependencies</label><br>

  				<input type="checkbox" id="optionUseLookup" name="optionUseLookup" value="1" <?php echo ($_SESSION["form_optionUseLookup"] == 1) ? 'checked' : ''; ?>>
  				<label for="optionUseLookup" class="info-legend info-legend-lookup">lookup</label><br>

  				<input type="checkbox" id="optionUseGetOneFree" name="optionUseGetOneFree" value="1" <?php echo ($_SESSION["form_optionUseGetOneFree"] == 1) ? 'checked' : ''; ?>>
  				<label for="optionUseGetOneFree" class="info-legend info-legend-getonefree">getOneFree</label><br>

  				<input type="checkbox" id="optionUseGetOneFreeProtected" name="optionUseGetOneFreeProtected" value="1" <?php echo ($_SESSION["form_optionUseGetOneFreeProtected"] == 1) ? 'checked' : ''; ?>>
  				<label for="optionUseGetOneFreeProtected" class="info-legend info-legend-getonefreeprotected">getOneFreeProtected (OXCE)</label><br>
  			</fieldset>
			<br />
			<br />
  			<p class="desc">Filter through:</p>
  			<fieldset>
				<label>Starting node name:</label>
				<div class="autoComplete_wrapper">
					<input type="text" id="autoComplete" name="optionLimitNodeName" dir="ltr" spellcheck=false autocorrect="off" autocomplete="off" autocapitalize="off" maxlength="2048" tabindex="1" value="<?php echo $_SESSION["form_optionLimitNodeName"]; ?>">
				</div>
				<br />
				<br />
				<label for="optionFollowDir">Follow into directions:</label>
				<select name="optionFollowDir" id="optionFollowDir">
				  <option value="0" <?php echo ($_SESSION["form_optionFollowDir"] == 0) ? 'selected' : ''; ?>>BOTH</option>
				  <option value="1" <?php echo ($_SESSION["form_optionFollowDir"] == 1) ? 'selected' : ''; ?>>FORWARD</option>
				  <option value="2" <?php echo ($_SESSION["form_optionFollowDir"] == 2) ? 'selected' : ''; ?>>REVERSE</option>
				</select>
				<br />
				<br />
				<label>Max depth (0 = unlimited):</label>
				<input type="number" id="optionMaxDepth" name="optionMaxDepth" value="<?php echo $_SESSION["form_optionMaxDepth"]; ?>" min="0" max="1000">
			</fieldset>
			<br />
			<br />
			<input type="submit" name="submit" id="submit" value="Generate Graph">
		</form>
	</div>

	<?php if (isset($dotscript)): ?>

		<div class="code-box">
			<p>Copy-Paste the below DOT (graph description language) code to your favorite <a href="https://graphviz.org/" target="_blank">Graphviz</a> generator or <a href="?download=true">download it as .gv file</a>.</p>
			<p>Some free online graphviz generators:</p>
			<ul>
				<li><a target="_blank" href="https://edotor.net/">https://edotor.net/</a></li>
				<li><a target="_blank" href="http://magjac.com/graphviz-visual-editor/">http://magjac.com/graphviz-visual-editor/</a></li>
				<li><a target="_blank" href="https://stamm-wilbrandt.de/GraphvizFiddle/">https://stamm-wilbrandt.de/GraphvizFiddle/</a></li>
				<li><a target="_blank" href="https://sketchviz.com/new">https://sketchviz.com/new</a></li>
			</ul>
			<textarea><?php echo htmlspecialchars($dotscript); ?></textarea>
		</div>

	<?php endif; ?>

	</div>

	<?php if (isset($dotscript)): ?>
		<div class="main-box">
			
			<script>
				var dotscript = <?php echo json_encode($dotscript); ?>;
			</script>


			<?php /*
			<div class="download-image"><a href="#" onclick="genAsSVG();return false;">Download as .svg</a></div>
			<div class="download-image"><a href="#" onclick="genAsSVG();return false;">Download as .svg</a> <a href="#" onclick="genAsImage();return false;">Download as .jpg</a></div>
			*/ ?>

			<p class="desc">Pan: mouse click &amp; drag - Zoom: mouse wheel+shift key</p>
			<div class="panzoom-parent">
				<div id="graph-rendered"></div>
			</div> 
			<script>
				var viz = new Viz();

				//viz.renderSVGElement("digraph { a -> b }")
				viz.renderSVGElement(dotscript)
				.then(function(element) {
					//document.body.appendChild(element);
					document.getElementById("graph-rendered").appendChild(element);
				})
				.catch(error => {
					// Create a new Viz instance (@see Caveats page for more info)
					viz = new Viz();

					// Possibly display the error
					console.error(error);
				});
				
				function genAsSVG() {
					viz.renderSVGElement(dotscript)
					.then(function(element) {
						//document.getElementById("graph-rendered").appendChild(element);

						//console.log(element);
						//saveAs(element.src, "graph.svg");

					    var blob = new Blob([element.outerHTML], {type: "image/svg+xml;charset=UTF-8"});
					    saveAs(blob, "graph.svg");
					})		  
					.catch(error => {
						// Create a new Viz instance (@see Caveats page for more info)
						viz = new Viz();

						// Possibly display the error
						console.error(error);
				  	});
				}
				
				function genAsImage() {
					viz.renderImageElement(dotscript, {scale:0.6, mimeType: "image/jpeg", quality: 0.9})
					.then(function(element) {
						//document.getElementById("graph-rendered").appendChild(element);

						saveAs(element.src, "graph.jpg");

					    //var blob = new Blob([element.src], {type: "image/png"});
					    //saveAs(blob, "hello world.png");
					})		  
					.catch(error => {
						// Create a new Viz instance (@see Caveats page for more info)
						viz = new Viz();

						// Possibly display the error
						console.error(error);
				  	});
				}

				const elem = document.getElementById('graph-rendered');
				const panzoom = Panzoom(elem)
				//panzoom.zoom(0.3);
				//panzoom.pan(100, 100);
				//panzoom.reset();
				//elem.parentElement.addEventListener('wheel', panzoom.zoomWithWheel);
				elem.parentElement.addEventListener('wheel', function (event) {
				  if (!event.shiftKey) return
				  // Panzoom will automatically use `deltaX` here instead
				  // of `deltaY`. On a mac, the shift modifier usually
				  // translates to horizontal scrolling, but Panzoom assumes
				  // the desired behavior is zooming.
				  panzoom.zoomWithWheel(event)
				});
				/*
				const panzoom = Panzoom(elem, {
				  maxScale: 5
				});
				panzoom.pan(10, 10);
				panzoom.zoom(2, { animate: true });

				// Panning and pinch zooming are bound automatically (unless disablePan is true).
				// There are several available methods for zooming
				// that can be bound on button clicks or mousewheel.
				button.addEventListener('click', panzoom.zoomIn);
				elem.parentElement.addEventListener('wheel', panzoom.zoomWithWheel);
				*/

			</script>

		</div>
	<?php endif; ?>
	</div>

	<div class="TODO">
		<h3>TODO</h3>
		<ul>
			<li>Improve graph readability</li>
			<li>Add OXCE features (getOneFreeProtected, etc..)</li>
			<li>Add graph legend</li>
		</ul>
	</div>

	<div class="download-source-code">
		<p>Warning: spaghetti code ahead!!!</p>
		<a href="?downloadsource=true">Download source code of this page</a>
	</div>


	<?php 

	$json_autocomplete = json_encode(array());
	if (isset($arrayNodes)) {
		$json_autocomplete = json_encode(array_keys($arrayNodes));
	}
	?>

	<script type="text/javascript">
		// // autoComplete.js input eventListener on connect event
		// document.querySelector("#autoComplete").addEventListener("connect", function (event) {
		//   console.log(event);
		// });
		// autoComplete.js input eventListener on initialization event
		/*
		document.querySelector("#autoComplete").addEventListener("init", function (event) {
		  console.log(event);
		});
		*/
		// // autoComplete.js input eventListener on input event
		// document.querySelector("#autoComplete").addEventListener("input", function (event) {
		//   console.log(event);
		// });
		// // autoComplete.js input eventListener on data response event
		// document.querySelector("#autoComplete").addEventListener("fetch", function (event) {
		//   console.log(event.detail);
		// });
		// // autoComplete.js input eventListener on search results event
		// document.querySelector("#autoComplete").addEventListener("results", function (event) {
		//   console.log(event.detail);
		// });
		// // autoComplete.js input eventListener on post results list rendering event
		// document.querySelector("#autoComplete").addEventListener("rendered", function (event) {
		//   console.log(event.detail);
		// });
		// // autoComplete.js input eventListener on results list navigation
		// document.querySelector("#autoComplete").addEventListener("navigation", function (event) {
		//   console.log(event.detail);
		// });
		// // autoComplete.js input eventListener on post un-initialization event
		// document.querySelector("#autoComplete").addEventListener("unInit", function (event) {
		//   console.log(event);
		// });

		// The autoComplete.js Engine instance creator
		const autoCompleteJS = new autoComplete({
		  name: "Node name",
		  selector: "#autoComplete",
		  observer: false,
		  data: {
		  	src: <?php echo $json_autocomplete; ?>,
		    cache: true,
		  },
		  searchEngine: "strict",
		  placeHolder: "ex. STR_POWER_SUIT",
		  maxResults: 10,
		  sort: (a, b) => {
		    if (a.match < b.match) return -1;
		    if (a.match > b.match) return 1;
		    return 0;
		  },
		  highlight: true,
		  debounce: 100,
		  threshold: 1,
		  trigger: {
		    event: ["input", "focus"],
		  },
		  resultItem: {
		    content: (data, element) => {
		      // Modify Results Item Style
		      element.style = "display: flex; justify-content: space-between;";
		      // Modify Results Item Content
		      element.innerHTML = `
		        <span style="text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">
		            ${data.match}
		        </span>`;
		    },
		  },
		  onSelection: (feedback) => {
		    document.querySelector("#autoComplete").blur();
		    const selection = feedback.selection.value;
		    // Replace Input value with the selected value
		    document.querySelector("#autoComplete").value = selection;
		    // Console log autoComplete data feedback
		    //console.log(feedback);
		  },
		});

		// autoComplete.unInit();
	</script>

</body>
</html>
