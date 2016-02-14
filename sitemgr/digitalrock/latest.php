<?php
/* $Id$ */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) &&
	$_SERVER['HTTP_HOST'] != 'localhost' && substr($_SERVER['HTTP_HOST'], -6) != '.local')
{
	include_once('cache.php');
	do_cache();
}
require_once('open_db.inc.php');

if (!$t_latest_result)	// happens if more then one ranking_digitalrock module is on one page in sitemgr
{
	include('en.inc.php');
}
if (!defined('DR_PATH')) {	// we are not running inside sitemgr
?>
<!doctype html public "-//W3C//DTD HTML 3.2 Final//EN">
<html>
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
   <meta name="Author" content="Ralf Becker [http://www.digitalROCK.de]" />
   <meta name="GENERATOR" content="<?php echo substr($_SERVER['PHP_SELF'],1); ?> (c) 2001-<?php echo date('Y'); ?> by Ralf Becker" />
   <meta name="KeyWords" content="digital ROCK, Klettern, Wettkampfklettern, Sportklettern, climbing, climbing competitions, UIAA, DAV, ICC, EYC, worldcup, worldranking, CUWR" />
   <base target="profil" />
   <title>digital ROCK: <?php echo $t_latest_result; ?></title>
</head>
<body text="#000000" bgcolor="#FFFFFF">
<?php
}

$header = get_param('header',array('POST','GET'));
$nation = get_param('nation',array('POST','GET'));
$w_rkey = get_param('w_rkey',array('POST','GET'));
$replace= get_param('replace',array('POST','GET'));
$with   = get_param('with',array('POST','GET'));

echo "<table width='100%'><tr><td>\n";
if (!$header) $header=$t_latest_result;
echo "<h3>$header</h3>\n";
echo "</td><td align=right>\n";

$res = my_query("SELECT DISTINCT w.* FROM Wettkaempfe w,Results r".
                " WHERE w.WetId=r.WetId AND ".
		($nation ? "w.nation='$nation'" : "ISNULL(w.nation)").
		" AND r.platz=1 ORDER BY w.datum DESC LIMIT 20");

$wettk = $w = mysqli_fetch_object($res);

if ($w_rkey == '')
{
	$w_rkey = $replace == $wettk->rkey ? $with : $wettk->rkey;
}
echo '<form action="'.$_SERVER['PHP_SELF'].'" method="GET" target="_self">'."\n";
foreach($_GET+array('header' => $header) as $name => $value)
{
	if ($name != 'w_rkey') echo "\t".'<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'">'."\n";
}
echo "\t".'<select onchange="this.form.submit()" name="w_rkey">'."\n";
do
{
	if ($w_rkey && $w->rkey == $w_rkey)
	{
		$wettk = $w;
	}
	else
	{
		echo "\t\t".'<option value="'.$w->rkey.'">'.$w->name."</option>\n";
	}
}
while ($w = mysqli_fetch_object($res));

echo "\t</select>\n";
echo "\t<input type=\"submit\" value=\"Go\">\n";
echo "</form>\n";
echo "</td></tr></table>\n";

unset($nation);	// otherwise all results from that nation will be shown

include ('all_result.php');
