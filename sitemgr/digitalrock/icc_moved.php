<?php
die('Hallo Ralf');
   list($page) = explode('.',basename($_SERVER['PHP_SELF']));
   if ($page == 'icc_calendar') $page = 'calendar';
   $new="http://www.uiaaclimbing.com/index.php?page_name=$page";
   if ($_SERVER['QUERY_STRING']) $new .= '&'.$_SERVER['QUERY_STRING'];
?>
<html>
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
   <meta name="Author" CONTENT="Ralf Becker" />
   <meta http-equiv="refresh" content="5; URL=<?php echo $new ?>" />
   <title>www.icc-info.org is now www.uiaaclimbing.com</title>
</head>
<body>
<h3>www.icc-info.org is now www.uiaaclimbing.com</h3>
<p />
<p align="center"><img src="img/uiaa-icc.gif" ALT="UIAA Climbing" /></p>
<p />
<p><b>The URL of the requested page has been changed !!!</b></p>
<p />
<p><a href="<?php echo htmlspecialchars($new); ?>"><?php echo htmlspecialchars($new); ?></a></p>
<p />
<p>Please note the new URL - you will be redirected shortly.</p>
</body>
</html>
