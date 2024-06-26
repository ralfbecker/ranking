<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<meta name="Author" content="Ralf Becker [http://www.digitalROCK.de]" />
		<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
		<script type="text/javascript" src="sitemgr/digitalrock/dr_api.js"></script>
		<link type="text/css" rel="StyleSheet" href="sitemgr/digitalrock/dr_list.css" />
	</head>
	<body>
		<div id="table"></div>
		<script type="text/javascript">
			var resultlist;
			$(document).ready(function() {
				resultlist = new Resultlist('table',location.pathname.replace(/result.php$/,'/json.php')+location.search);

				setTimeout(function(){window.scrollTo(0, 1);}, 100);
			});

			if (document.location.href.match(/beamer=1/)) load_css('sitemgr/digitalrock/beamer.css');
		</script>
	</body>
</html>
