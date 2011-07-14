<!-- BEGIN header -->
<form method="POST" action="{action_url}">
{hidden_vars}
<table border="0" align="center">
   <tr class="error" align="center">
    <td colspan="2">&nbsp;<b>{error}</b></td>
   </tr>
   <tr class="th">
    <td colspan="2">&nbsp;<b>{title}</b></td>
   </tr>
<!-- END header -->

<!-- BEGIN body -->
   <tr class="row_on">
    <td>{lang_VFS_directory_for_the_pdf_files_(excluding_the_year)}</td>
    <td><input name="newsettings[vfs_pdf_dir]" value="{value_vfs_pdf_dir}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_VFS_directory_for_topo_files_(excluding_the_year)}</td>
    <td><input name="newsettings[vfs_topo_dir]" value="{value_vfs_topo_dir}"></td>
   </tr>
   <tr class="th">
    <td colspan="2"><b>{lang_Ranking_database}</b> ({lang_only_fill_values_different_from_eGW}):</td>
   </tr>
   <tr class="row_on">
    <td>{lang_Charset}:</td>
    <td><input size="8" name="newsettings[ranking_db_charset]" value="{value_ranking_db_charset}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_Host}:</td>
    <td><input name="newsettings[ranking_db_host]" value="{value_ranking_db_host}"></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Port}:</td>
    <td><input name="newsettings[ranking_db_port]" value="{value_ranking_db_port}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_Database}:</td>
    <td><input name="newsettings[ranking_db_name]" value="{value_ranking_db_name}"></td>
   </tr>
   <tr class="row_on">
    <td>{lang_User}:</td>
    <td><input name="newsettings[ranking_db_user]" value="{value_ranking_db_user}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_Password}:</td>
    <td><input type="password" name="newsettings[ranking_db_pass]" value="{value_ranking_db_pass}"></td>
   </tr>

   <tr class="th">
    <td colspan="2"><b>Autoimport from rock programms</b>:</td>
   </tr>
   <tr class="row_on">
    <td>Competition to use:</td>
    <td>
     <select name="newsettings[rock_import_calendar]">{hook_rock_import_calendar}</select>
     <select name="newsettings[rock_import_comp]">{hook_rock_import_comp}</select>
    </td>
   </tr>
   <tr class="row_off">
    <td>Path for rock files to import (without year):</td>
    <td>
     <input name="newsettings[rock_import_path]" value="{value_rock_import_path}">
    </td>
   </tr>
   <tr class="row_on">
    <td>1. route to import (rock-route/category/heat):</td>
    <td>
    	<input name="newsettings[rock_import1]" value="{value_rock_import1}">
    	<select name="newsettings[rock_import_cat1]">{hook_rock_import_cat1}</select>
     	<select name="newsettings[rock_import_route1]">{hook_rock_import_route1}</select>
   </td>
   <tr class="row_off">
    <td>2. route to import (rock-route/category/heat):</td>
    <td>
    	<input name="newsettings[rock_import2]" value="{value_rock_import2}">
    	<select name="newsettings[rock_import_cat2]">{hook_rock_import_cat2}</select>
     	<select name="newsettings[rock_import_route2]">{hook_rock_import_route2}</select>
   </td>
<!-- END body -->

<!-- BEGIN footer -->
  <tr class="th">
    <td colspan="2">
&nbsp;
    </td>
  </tr>
  <tr>
    <td colspan="2" align="center">
      <input type="submit" name="submit" value="{lang_submit}">
      <input type="submit" name="cancel" value="{lang_cancel}">
    </td>
  </tr>
</table>
</form>
<!-- END footer -->
