<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<title>{sitename}: {title}</title>

	<meta http-equiv="Content-Type" content="text/html; charset={charset}">

	{editmode_styles}

	<link href="templates/default/style/default.css" type="text/css" rel="StyleSheet" />
	<link href="templates/icc/style.css" type="text/css" rel="StyleSheet" />
</head>
<body style="padding: 8px; margin: 0px;">
<table cellspacing="0" cellpadding="0" style="width: 100%; padding: 0px; border-style: none;"><tbody>
	<tr style="height: 34px;" class="noPrint">
		<td colspan="7"><a href="/"><img src="templates/icc/images/ifsc_header.gif" style="position: absolute; left:100px; top:5px; border: none;" /></a></td>
	</tr>
	<tr style="height: 99px;" class="noPrint">
		<td style="background: url(templates/icc/images/header-left.gif) no-repeat; width: 3px; height: 99px;"></td>
		<td style="background: url(templates/icc/images/header-main.gif) repeat-x; width: 147px; height: 99px;"></td>
		<td colspan="4" style="background: url(templates/icc/images/header-main.gif) repeat-x; height: 99px; padding-left: 35px;">
			<!--img src="templates/icc/images/climber.gif" style="position: absolute; left:128px; top:85px; z-index: 1;" /-->
			<div style="font-size: 10pt; color: white; position: absolute; left:200px; top:63px; right: 15px; z-index: 5;">{sitename}</div>
			<div style="font-size: 10pt; position: absolute; left:200px; right: 15px; top:95px; ">{contentarea:header}</div>
		</td>
		<td style="background: url(templates/icc/images/header-right.gif) no-repeat; width: 5px; height: 99px;"></td>
	</tr>
	<tr style="height: 14px;" class="noPrint">
		<td colspan="2" valign="top" rowspan="3" style="width: 150px;">
			{contentarea:left}
			{contentarea:left2}
		</td>
		<td style="background: url(templates/icc/images/top-left.gif) no-repeat; width: 16px; height: 14px;"></td>
		<td style="background: url(templates/icc/images/top-main.gif) repeat-x; height: 14px;"> </td>
		<td style="background: url(templates/icc/images/top-sep.gif) no-repeat; width: 9px; height: 14px;"></td>
		<td style="background: url(templates/icc/images/top-main.gif) repeat-x #bcd8e4; width: 120px; height: 14px;"> </td>
		<td style="background: url(templates/icc/images/top-right.gif) no-repeat; width: 5px; height: 14px;"></td>
	</tr>
	<tr height="400">
		<td style="background: url(templates/icc/images/middle-left.gif) repeat-y; width: 16px;" class="noPrint"></td>
		<td valign="top" style="overflow: hidden; padding-right: 8px;">
			<p style="margin-bottom: 0px;" id="divAppboxHeader"><strong>{title}</strong> {editicons}</p>
			<div id="divAppbox">
				{contentarea:center}
			</div>
		</td>
		<td style="background: url(templates/icc/images/middle-sep.gif) repeat-y; width: 9px;" class="noPrint"></td>
		<td valign="top" style="background-color: #bcd8e4; padding-left: 5px; overflow: hidden;" class="noPrint">
			{contentarea:right}
		</td>
		<td style="background: url(templates/icc/images/middle-right.gif) repeat-y; width: 5px;" class="noPrint"></td>
	</tr>
	<tr style="height: 12px;" class="noPrint">
		<td style="background: url(templates/icc/images/bottom-left.gif) no-repeat; width: 16px; height: 12px;"></td>
		<td colspan="2" style="background: url(templates/icc/images/bottom-main.gif) repeat-x; height: 12px;"></td>
		<td style="background: url(templates/icc/images/bottom-main.gif) repeat-x #bcd8e4; height: 12px;"></td>
		<td style="background: url(templates/icc/images/bottom-right.gif); width: 3px no-repeat; height: 12px;"></td>
	</tr>
</tbody></table>
<div style="text-align: center;" class="noPrint">
	{contentarea:footer}
</div>

<script src="http://www.google-analytics.com/urchin.js" type="text/javascript">
</script>
<script type="text/javascript">
_uacct = "UA-420419-2";
urchinTracker();
</script>

</body>
</html>
