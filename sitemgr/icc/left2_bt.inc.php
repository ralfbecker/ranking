<?php
	/**************************************************************************\
	* eGroupWare SiteMgr - Web Content Management                              *
	* http://www.egroupware.org                                                *
	* Copyright (c) 2004 by RalfBecker@outdoor-training.de                     *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

class left2_bt
{
	function apply_transform($title,$content)
	{
		return '
<table background="templates/icc/images/hg-blau.jpg" border="0" cellpadding="0" cellspacing="0" width="158"><tbody>
	<tr>
		<td colspan="3" height="7" width="158"> <div style="font-size: 1pt;"><img src="templates/icc/images/3d-links-oben.jpg" height="7" width="158"></div></td>
	</tr>
	<tr>
		<td width="10">&nbsp;</td>
		<td valign="top" width="139"><div style="font-size: 10pt; overflow: hidden;">
			<font color="#ffffff">'.$title.'</font><p>
'.$content.'
		</div></td>
		<td background="templates/icc/images/3d-links.jpg" width="9">&nbsp;</td>
	</tr>
	<tr>
		<td colspan="3" height="16" width="139"><img src="templates/icc/images/3d-links-unten.jpg" height="16" width="158"></td>
	</tr>
</tbody></table>';
	}
}
