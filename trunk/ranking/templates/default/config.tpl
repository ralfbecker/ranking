<!-- BEGIN header -->
<form method="POST" action="{action_url}">
{hidden_vars}
<table border="0" align="center">
   <tr class="th">
    <td colspan="2">&nbsp;<b>{title}</b></td>
   </tr>
<!-- END header -->

<!-- BEGIN body -->
   <tr class="row_on">
    <td>{lang_VFS_directory_for_the_pdf_files_(excluding_the_year)}</td>
    <td><input name="newsettings[vfs_pdf_dir]" value="{value_vfs_pdf_dir}"></td>
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
    <td><input name="newsettings[ranking_db_pass]" value="{value_ranking_db_pass}"></td>
   </tr>
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
